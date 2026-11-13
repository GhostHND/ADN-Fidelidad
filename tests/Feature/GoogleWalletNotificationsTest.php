<?php

namespace Tests\Feature;

use App\Models\GoogleWalletNotification;
use App\Models\RewardEntitlement;
use App\Models\WalletSyncJob;
use App\Services\LoyaltyService;
use App\Services\Wallet\GoogleWalletNotificationPreflightException;
use App\Services\Wallet\GoogleWalletNotifications;
use App\Services\Wallet\GoogleWalletProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class GoogleWalletNotificationsTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_rewards_take_priority_and_a_slot_is_reserved(): void
    {
        $this->freezeTime();
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        $loyalty = app(LoyaltyService::class);
        for ($i = 1; $i <= 4; $i++) {
            $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'award-'.$i);
        }
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->twice();
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([2, 0, 2], $result);
        $this->assertDatabaseHas('google_wallet_notifications', ['kind' => 'reward', 'status' => 'sent']);
        $this->assertSame(1, GoogleWalletNotification::where('kind', 'stamp')->where('status', 'sent')->count());
        $this->assertSame(2, GoogleWalletNotification::where('kind', 'stamp')->where('status', 'skipped')->count());
        $this->assertSame(4, GoogleWalletNotification::count());
    }

    public function test_award_is_idempotent_and_additional_changes_queue_separate_syncs(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        app(GoogleWalletProvider::class)->ensurePass($card);
        $loyalty = app(LoyaltyService::class);

        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'same-key');
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'same-key');
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'next-key');

        $this->assertSame(2, GoogleWalletNotification::count());
        $this->assertSame(2, WalletSyncJob::where('card_id', $card->id)->where('status', 'pending')->count());
    }

    public function test_unsynced_cards_wait_for_sync_and_only_the_selected_business_is_processed(): void
    {
        $first = $this->makeBusiness('Uno');
        $second = $this->makeBusiness('Dos');
        $card = $this->makeCard($first['business'], $first['program']);
        $other = $this->makeCard($second['business'], $second['program'], 'other@t.test');
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        app(GoogleWalletProvider::class)->ensurePass($other);
        app(LoyaltyService::class)->award($card, $first['cashier'], null, ['quantity' => 1], 'uno');
        app(LoyaltyService::class)->award($other, $second['cashier'], null, ['quantity' => 1], 'dos');
        $pass->update(['status' => 'installed_verified', 'synced_revision' => 0]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldNotReceive('sendNotification');
        });

        $result = app(GoogleWalletNotifications::class)->processDue(50, $first['business']->id);

        $this->assertSame([0, 0, 0], $result);
        $this->assertSame(2, GoogleWalletNotification::where('status', 'pending')->count());
    }

    public function test_uncertain_delivery_is_not_retried_and_counts_toward_the_limit(): void
    {
        $this->freezeTime();
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        $loyalty = app(LoyaltyService::class);
        for ($i = 1; $i <= 3; $i++) {
            $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'failure-'.$i);
        }
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->twice()->andThrow(new \RuntimeException('Sin respuesta'));
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([0, 2, 1], $result);
        $this->assertSame(2, GoogleWalletNotification::where('status', 'uncertain')->count());
        $this->assertSame([0, 0, 0], app(GoogleWalletNotifications::class)->processDue());
    }

    public function test_google_receives_a_text_and_notify_message(): void
    {
        config(['fidelidad.wallet.google.issuer_id' => '123', 'fidelidad.wallet.google.service_account_json' => __FILE__]);
        Cache::put('google_wallet_token', 'test-token', 300);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $provider = app(GoogleWalletProvider::class);
        $pass = $provider->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'http-test');
        $notification = GoogleWalletNotification::firstOrFail();
        Http::preventStrayRequests();
        Http::fake([
            'walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*/addMessage' => Http::response([], 200),
            'walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*' => Http::response(['messages' => []], 200),
        ]);

        $provider->sendNotification($pass, $notification);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/addMessage')
            && $request['message']['messageType'] === 'TEXT_AND_NOTIFY'
            && $request['message']['id'] === 'adnf_n_'.$notification->id);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->body() === '');
    }

    public function test_slot_is_available_again_after_24_hours(): void
    {
        $this->freezeTime();
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        $loyalty = app(LoyaltyService::class);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'old-1');
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'old-2');
        GoogleWalletNotification::query()->update(['status' => 'sent', 'attempted_at' => now()->subHours(24)]);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'new-1');
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->once();
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([1, 0, 0], $result);
        $this->assertSame(3, GoogleWalletNotification::where('status', 'sent')->count());
    }

    public function test_notifications_do_not_remove_messages_owned_by_others(): void
    {
        config(['fidelidad.wallet.google.issuer_id' => '123', 'fidelidad.wallet.google.service_account_json' => __FILE__]);
        Cache::put('google_wallet_token', 'test-token', 300);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $provider = app(GoogleWalletProvider::class);
        $pass = $provider->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'full');
        $notification = GoogleWalletNotification::firstOrFail();
        Http::preventStrayRequests();
        Http::fake([
            'walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*' => Http::response([
                'messages' => array_map(fn ($i) => ['id' => 'other-'.$i], range(1, 10)),
            ], 200),
        ]);

        try {
            $provider->sendNotification($pass, $notification);
            $this->fail('No debe eliminar mensajes ajenos.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('10 mensajes', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_unsaved_pass_is_skipped_after_the_sync_is_finished(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        app(GoogleWalletProvider::class)->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'not-saved');
        WalletSyncJob::where('card_id', $card->id)->update(['status' => 'done']);
        $this->mock(GoogleWalletProvider::class, fn ($mock) => $mock->shouldNotReceive('sendNotification'));

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([0, 0, 1], $result);
        $this->assertDatabaseHas('google_wallet_notifications', ['status' => 'skipped']);
    }

    public function test_reward_notification_is_skipped_if_the_reward_was_redeemed(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        $loyalty = app(LoyaltyService::class);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 3], 'reward-first');
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'reward-second');
        $entitlement = RewardEntitlement::withoutGlobalScopes()->where('card_id', $card->id)->firstOrFail();
        $loyalty->redeem($entitlement, $scenario['cashier'], null);
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->once()
                ->withArgs(fn ($pass, $notification) => $notification->kind === 'stamp');
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([1, 0, 1], $result);
        $this->assertDatabaseHas('google_wallet_notifications', ['kind' => 'reward', 'status' => 'skipped']);
    }

    public function test_missing_remote_object_is_not_left_as_a_verified_installation(): void
    {
        config(['fidelidad.wallet.google.issuer_id' => '123', 'fidelidad.wallet.google.service_account_json' => __FILE__]);
        Cache::put('google_wallet_token', 'test-token', 300);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $provider = app(GoogleWalletProvider::class);
        $pass = $provider->ensurePass($card);
        $pass->update(['status' => 'installed_verified', 'verified_at' => now()]);
        Http::preventStrayRequests();
        Http::fake(['walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*' => Http::response([], 404)]);

        $provider->syncCard($pass);

        $this->assertSame('link_opened', $pass->fresh()->status);
        $this->assertNull($pass->fresh()->verified_at);
        Http::assertSentCount(1);
    }

    public function test_wallet_command_synchronizes_before_sending_notification(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'command');
        $this->mock(GoogleWalletProvider::class, function ($mock) use ($card) {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('syncCard')->once()->ordered()->andReturnUsing(
                fn ($pass) => $pass->update(['synced_revision' => $card->fresh()->revision])
            );
            $mock->shouldReceive('sendNotification')->once()->ordered();
        });

        $this->artisan('wallet:process', ['--business' => $scenario['business']->id, '--platform' => 'google'])
            ->assertSuccessful();

        $this->assertDatabaseHas('google_wallet_notifications', ['wallet_pass_id' => $pass->id, 'status' => 'sent']);
    }

    public function test_preflight_failure_is_retried_without_using_a_notification_slot(): void
    {
        $this->freezeTime();
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'preflight');
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->twice()
                ->andThrow(new GoogleWalletNotificationPreflightException('Error previo al envío'));
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([0, 1, 0], $result);
        $this->assertDatabaseHas('google_wallet_notifications', [
            'status' => 'pending', 'attempts' => 1, 'attempted_at' => null, 'last_error' => 'Error previo al envío',
        ]);
        $this->assertSame([0, 0, 0], app(GoogleWalletNotifications::class)->processDue());
        $this->travel(2)->minutes();
        $this->assertSame([0, 1, 0], app(GoogleWalletNotifications::class)->processDue());
        $this->assertDatabaseHas('google_wallet_notifications', ['status' => 'pending', 'attempts' => 2]);
    }

    public function test_preflight_retries_stop_after_the_configured_maximum(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $pass = app(GoogleWalletProvider::class)->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'preflight-max');
        $pass->update(['status' => 'installed_verified', 'synced_revision' => $card->fresh()->revision]);
        GoogleWalletNotification::query()->update(['attempts' => 5]);
        $this->mock(GoogleWalletProvider::class, function ($mock) {
            $mock->shouldReceive('sendNotification')->once()
                ->andThrow(new GoogleWalletNotificationPreflightException('Error previo al envío'));
        });

        $result = app(GoogleWalletNotifications::class)->processDue();

        $this->assertSame([0, 1, 0], $result);
        $this->assertDatabaseHas('google_wallet_notifications', ['status' => 'failed', 'attempts' => 6, 'attempted_at' => null]);
    }

    public function test_old_owned_message_is_removed_when_the_google_message_list_is_full(): void
    {
        config(['fidelidad.wallet.google.issuer_id' => '123', 'fidelidad.wallet.google.service_account_json' => __FILE__]);
        Cache::put('google_wallet_token', 'test-token', 300);
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        $provider = app(GoogleWalletProvider::class);
        $pass = $provider->ensurePass($card);
        $loyalty = app(LoyaltyService::class);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'old-message');
        $old = GoogleWalletNotification::firstOrFail();
        $old->update(['status' => 'sent', 'attempted_at' => now()->subDays(2)]);
        $loyalty->award($card, $scenario['cashier'], null, ['quantity' => 1], 'new-message');
        $notification = GoogleWalletNotification::where('status', 'pending')->firstOrFail();
        $messages = array_map(fn ($i) => ['id' => 'other-'.$i], range(1, 9));
        $messages[] = ['id' => 'adnf_n_'.$old->id];
        Http::preventStrayRequests();
        Http::fake([
            'walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*/addMessage' => Http::response([], 200),
            'walletobjects.googleapis.com/walletobjects/v1/loyaltyObject/*' => Http::response(['messages' => $messages], 200),
        ]);

        $provider->sendNotification($pass, $notification);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && count($request['messages']) === 9
            && $request['messages'][0]['id'] === 'other-1');
        Http::assertSentCount(3);
    }
}

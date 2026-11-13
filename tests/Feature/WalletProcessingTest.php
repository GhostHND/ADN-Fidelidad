<?php

namespace Tests\Feature;

use App\Models\WalletSyncJob;
use App\Services\LoyaltyService;
use App\Services\Wallet\GoogleWalletProvider;
use App\Services\Wallet\WalletManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\Support\BuildsScenario;
use Tests\TestCase;

class WalletProcessingTest extends TestCase
{
    use BuildsScenario, RefreshDatabase;

    public function test_filtered_processing_leaves_other_businesses_and_platforms_pending(): void
    {
        $first = $this->makeBusiness('Uno');
        $second = $this->makeBusiness('Dos');
        $create = fn ($business, $program, $platform) => WalletSyncJob::create([
            'business_id' => $business->id, 'program_id' => $program->id,
            'platform' => $platform, 'scope' => 'class', 'reason' => 'test',
            'status' => 'pending', 'next_attempt_at' => now(),
        ]);
        $target = $create($first['business'], $first['program'], 'google');
        $otherBusiness = $create($second['business'], $second['program'], 'google');
        $otherPlatform = $create($first['business'], $first['program'], 'apple');
        $this->mock(GoogleWalletProvider::class, function ($mock) use ($first) {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('syncProgram')->once()->with(Mockery::on(fn ($program) => $program->id === $first['program']->id));
        });

        $this->artisan('wallet:process', ['--business' => $first['business']->id, '--platform' => 'google'])
            ->assertSuccessful();

        $this->assertSame('done', $target->fresh()->status);
        $this->assertSame('pending', $otherBusiness->fresh()->status);
        $this->assertSame('pending', $otherPlatform->fresh()->status);
    }

    public function test_invalid_filters_are_rejected_without_processing(): void
    {
        $scenario = $this->makeBusiness();
        $job = WalletSyncJob::create([
            'business_id' => $scenario['business']->id, 'program_id' => $scenario['program']->id,
            'platform' => 'google', 'scope' => 'class', 'reason' => 'test',
            'status' => 'pending', 'next_attempt_at' => now(),
        ]);

        $this->artisan('wallet:process', ['--business' => 'invalid'])->assertFailed();
        $this->artisan('wallet:process', ['--platform' => 'invalid'])->assertFailed();
        $this->artisan('wallet:process', ['--limit' => '0'])->assertFailed();

        $this->assertSame('pending', $job->fresh()->status);
    }

    public function test_change_during_sync_leaves_another_job_pending(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        app(GoogleWalletProvider::class)->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'before-sync');
        $this->mock(GoogleWalletProvider::class, function ($mock) use ($scenario, $card) {
            $mock->shouldReceive('isConfigured')->once()->andReturnTrue();
            $mock->shouldReceive('syncCard')->once()->andReturnUsing(function () use ($scenario, $card) {
                app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'during-sync');
            });
        });

        $result = app(WalletManager::class)->processDue();

        $this->assertSame([1, 0, 0], $result);
        $this->assertSame(1, WalletSyncJob::where('status', 'done')->count());
        $this->assertSame(1, WalletSyncJob::where('status', 'pending')->count());
    }

    public function test_locked_card_is_not_processed_by_another_worker(): void
    {
        $scenario = $this->makeBusiness();
        $card = $this->makeCard($scenario['business'], $scenario['program']);
        app(GoogleWalletProvider::class)->ensurePass($card);
        app(LoyaltyService::class)->award($card, $scenario['cashier'], null, ['quantity' => 1], 'locked');
        $lock = Cache::lock('wallet-sync-google-card-'.$card->id, 180);
        $this->assertTrue($lock->get());
        $this->mock(GoogleWalletProvider::class, fn ($mock) => $mock->shouldNotReceive('syncCard'));

        try {
            $result = app(WalletManager::class)->processDue();
        } finally {
            $lock->release();
        }

        $this->assertSame([0, 0, 0], $result);
        $this->assertSame(1, WalletSyncJob::where('status', 'pending')->count());
    }
}

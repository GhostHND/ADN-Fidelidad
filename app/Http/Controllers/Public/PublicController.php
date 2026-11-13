<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Services\LoyaltyService;
use App\Services\SubscriptionService;
use App\Services\Wallet\AppleWalletProvider;
use App\Services\Wallet\CardView;
use App\Services\Wallet\GoogleWalletProvider;
use App\Services\Wallet\StampStripRenderer;
use App\Services\Wallet\WalletManager;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function landing(string $slug, SubscriptionService $subscriptions)
    {
        $business = Business::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $program = $this->openProgram($business);

        return view('public.landing', [
            'business' => $business,
            'program' => $program,
            'available' => $program && $subscriptions->canOperate($business),
        ]);
    }

    public function register(Request $request, string $slug, LoyaltyService $loyalty, SubscriptionService $subscriptions)
    {
        $business = Business::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $program = $this->openProgram($business);
        abort_unless($program && $subscriptions->canOperate($business), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]{7,20}$/', 'required_without:email'],
            'birthdate' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'consent_privacy' => ['accepted'],
            'consent_marketing' => ['nullable', 'boolean'],
        ]);

        $email = ! empty($data['email']) ? mb_strtolower(trim($data['email'])) : null;
        $phone = ! empty($data['phone']) ? preg_replace('/[^0-9+]/', '', $data['phone']) : null;

        $card = DB::transaction(function () use ($business, $program, $data, $email, $phone, $loyalty) {
            $customer = Customer::withTrashed()
                ->where(fn ($q) => $email ? $q->where('email', $email) : $q->whereRaw('1=0'))
                ->orWhere(fn ($q) => $phone ? $q->where('phone', $phone) : $q->whereRaw('1=0'))
                ->first();
            if ($customer?->trashed() || $customer?->anonymized_at) {
                $customer = null;
            }
            $created = false;
            if (! $customer) {
                $customer = Customer::create(['name' => $data['name'], 'email' => $email, 'phone' => $phone, 'birthdate' => $data['birthdate'] ?? null]);
                $created = true;
            }
            $existingCard = CustomerCard::withoutGlobalScopes()->where('customer_id', $customer->id)->where('program_id', $program->id)->exists();
            if ($existingCard) {
                return null;
            }
            DB::table('customer_business')->updateOrInsert(
                ['business_id' => $business->id, 'customer_id' => $customer->id],
                ['status' => 'active', 'consent_privacy' => true, 'consent_marketing' => (bool) ($data['consent_marketing'] ?? false), 'consented_at' => now(), 'created_at' => now(), 'updated_at' => now()]
            );
            $card = $loyalty->issueCard($business, $program, $customer);
            AuditLog::record('customer.registered', $business, $card, ['new_customer' => $created]);

            return $card;
        });

        if (! $card) {
            // No se entrega el enlace: evita que un tercero acceda a la tarjeta de otra persona solo conociendo su correo.
            return back()->withInput()->with('error', 'Ya existe una tarjeta con esos datos. Usa el enlace que recibiste al registrarte o pide ayuda en el negocio.');
        }

        return redirect()->route('public.card', $card->uuid);
    }

    public function card(string $uuid, WalletManager $wallet)
    {
        $card = $this->findCard($uuid);
        $view = CardView::for($card);
        $business = $view->business();

        return response()->view('public.card', [
            'card' => $card,
            'view' => $view,
            'business' => $business,
            'qrPayload' => 'ADNF:'.$card->qr_token,
            'apple' => $wallet->available($business, 'apple'),
            'appleInDevelopment' => ! config('fidelidad.wallet.apple.enabled'),
            'google' => $wallet->available($business, 'google'),
            'anonymized' => (bool) $card->customer->anonymized_at,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function strip(string $uuid, int $revision, StampStripRenderer $renderer)
    {
        $card = $this->findCard($uuid);

        return response($renderer->render(CardView::for($card)), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function qr(string $uuid)
    {
        $card = $this->findCard($uuid);
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd));

        return response($writer->writeString('ADNF:'.$card->qr_token), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function applePass(string $uuid, WalletManager $wallet, AppleWalletProvider $apple)
    {
        $card = $this->findCard($uuid);
        abort_unless($wallet->available(Business::findOrFail($card->business_id), 'apple'), 404, 'Apple Wallet no está disponible.');

        try {
            $binary = $apple->buildPkpass($card);
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'No se pudo generar el pase de Apple Wallet en este momento.');
        }
        $apple->ensurePass($card)->update(['link_opened_at' => now(), 'status' => 'link_opened']);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="tarjeta.pkpass"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function google(string $uuid, WalletManager $wallet, GoogleWalletProvider $google)
    {
        $card = $this->findCard($uuid);
        abort_unless($wallet->available(Business::findOrFail($card->business_id), 'google'), 404, 'Google Wallet no está disponible.');

        try {
            $url = $google->installUrl($card);
        } catch (\Throwable $e) {
            report($e);
            abort(503, 'No se pudo generar el enlace de Google Wallet en este momento.');
        }
        $pass = $google->ensurePass($card);
        if ($pass->status !== 'installed_verified') {
            $pass->update(['link_opened_at' => now(), 'status' => 'link_opened']);
        }

        return redirect()->away($url);
    }

    public function anonymize(Request $request, string $uuid)
    {
        $card = $this->findCard($uuid);
        $request->validate(['confirm' => ['accepted']]);

        DB::transaction(function () use ($card) {
            $customer = Customer::findOrFail($card->customer_id);
            $customer->update(['name' => 'Cliente anonimizado', 'email' => null, 'phone' => null, 'birthdate' => null, 'anonymized_at' => now()]);
            DB::table('customer_business')->where('customer_id', $customer->id)->update(['consent_marketing' => false, 'status' => 'blocked']);
            AuditLog::record('customer.anonymized', Business::find($card->business_id), $card);
        });

        return redirect()->route('public.card', $uuid)->with('status', 'Tus datos personales fueron eliminados. Tu historial de sellos se conserva de forma anónima.');
    }

    public function manifest()
    {
        return response()->json([
            'name' => config('fidelidad.brand.name'),
            'short_name' => 'ADN Fidelidad',
            'lang' => 'es',
            'start_url' => '/login',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0FA7B4',
            'icons' => [['src' => '/brand/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    private function openProgram(Business $business): ?LoyaltyProgram
    {
        return LoyaltyProgram::withoutGlobalScopes()->where('business_id', $business->id)->where('status', 'active')->orderBy('id')->get()->first(fn ($p) => $p->isOpen());
    }

    private function findCard(string $uuid): CustomerCard
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $uuid), 404);

        return CustomerCard::withoutGlobalScopes()->with(['customer', 'program'])->where('uuid', $uuid)->firstOrFail();
    }
}

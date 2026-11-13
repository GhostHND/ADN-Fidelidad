<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppleWalletDevice;
use App\Models\AppleWalletRegistration;
use App\Models\CustomerCard;
use App\Models\WalletPass;
use App\Services\Wallet\AppleWalletProvider;
use Illuminate\Http\Request;

/** Servicio web PassKit: autenticado con "ApplePass {authenticationToken}" por pase. */
class AppleWebServiceController extends Controller
{
    public function register(Request $request, string $device, string $passType, string $serial)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);
        $push = $request->input('pushToken');
        abort_unless(is_string($push) && $push !== '' && strlen($push) <= 200, 400);

        $dev = AppleWalletDevice::updateOrCreate(['device_library_id' => $device], ['push_token' => $push]);
        $reg = AppleWalletRegistration::firstOrCreate(['device_id' => $dev->id, 'pass_id' => $pass->id]);
        if ($pass->status !== 'installed_verified') {
            $pass->update(['status' => 'installed_verified', 'verified_at' => now()]);
        }

        return response('', $reg->wasRecentlyCreated ? 201 : 200);
    }

    public function unregister(Request $request, string $device, string $passType, string $serial)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);
        $dev = AppleWalletDevice::where('device_library_id', $device)->first();
        if ($dev) {
            AppleWalletRegistration::where('device_id', $dev->id)->where('pass_id', $pass->id)->delete();
            if (! AppleWalletRegistration::where('device_id', $dev->id)->exists()) {
                $dev->delete();
            }
        }

        return response('', 200);
    }

    public function updated(Request $request, string $device, string $passType)
    {
        abort_unless($passType === config('fidelidad.wallet.apple.pass_type_id'), 404);
        $dev = AppleWalletDevice::where('device_library_id', $device)->first();
        if (! $dev) {
            return response('', 204);
        }
        $since = (int) $request->query('passesUpdatedSince', 0);
        $passes = WalletPass::where('platform', 'apple')
            ->whereIn('id', AppleWalletRegistration::where('device_id', $dev->id)->pluck('pass_id'))
            ->with('card')->get()
            ->filter(fn ($p) => $p->card && $p->card->revision > $since);
        // El protocolo PassKit no envía Authorization en esta consulta; solo se exponen seriales del dispositivo.
        if ($passes->isEmpty()) {
            return response('', 204);
        }

        return response()->json([
            'serialNumbers' => $passes->pluck('identifier')->values(),
            'lastUpdated' => (string) $passes->max(fn ($p) => $p->card->revision),
        ]);
    }

    public function pass(Request $request, string $passType, string $serial, AppleWalletProvider $apple)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);
        $card = CustomerCard::withoutGlobalScopes()->findOrFail($pass->card_id);
        $since = $request->header('If-Modified-Since');
        if ($since && $pass->last_synced_at && $card->updated_at && $card->updated_at->lte(\Carbon\Carbon::parse($since))) {
            return response('', 304);
        }

        return response($apple->buildPkpass($card), 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Last-Modified' => $card->updated_at->toRfc7231String(),
        ]);
    }

    public function log(Request $request)
    {
        foreach ((array) $request->input('logs', []) as $line) {
            logger()->channel('stack')->info('apple-wallet-log', ['line' => mb_substr((string) $line, 0, 500)]);
        }

        return response('', 200);
    }

    private function bearer(Request $request): ?string
    {
        $h = (string) $request->header('Authorization');

        return str_starts_with($h, 'ApplePass ') ? substr($h, 10) : null;
    }

    private function authorizedPass(Request $request, string $passType, string $serial): WalletPass
    {
        abort_unless($passType === config('fidelidad.wallet.apple.pass_type_id'), 404);
        $pass = WalletPass::where('platform', 'apple')->where('identifier', $serial)->first();
        $token = $this->bearer($request);
        abort_unless($pass && $token && hash_equals((string) $pass->auth_token, $token), 401);

        return $pass;
    }
}

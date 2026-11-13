<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\CustomerCard;
use App\Models\LoyaltyProgram;
use App\Models\RewardEntitlement;
use App\Models\StampTransaction;
use App\Services\LoyaltyException;
use App\Services\LoyaltyService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $cards = CustomerCard::with(['customer', 'program'])
            ->when($q !== '', fn ($query) => $query->whereHas('customer', fn ($c) => $c->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('phone', 'like', '%'.addcslashes($q, '%_\\').'%'))))
            ->when($request->query('program'), fn ($query, $id) => $query->where('program_id', (int) $id))
            ->when($request->query('filter') === 'inactive', fn ($query) => $query->where(fn ($w) => $w->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays(30))))
            ->when($request->query('filter') === 'reward', fn ($query) => $query->whereHas('entitlements', fn ($e) => $e->where('status', 'available')))
            ->orderByDesc('last_activity_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('business.customers.index', ['cards' => $cards, 'programs' => LoyaltyProgram::orderBy('name')->get(), 'q' => $q]);
    }

    public function show(CustomerCard $card)
    {
        $card->load(['customer', 'program']);

        return view('business.customers.show', [
            'card' => $card,
            'transactions' => StampTransaction::with('employee')->where('card_id', $card->id)->latest('id')->limit(50)->get(),
            'entitlements' => RewardEntitlement::with('reward')->where('card_id', $card->id)->latest('id')->get(),
            'reversed' => StampTransaction::where('card_id', $card->id)->whereNotNull('reverses_id')->pluck('reverses_id')->all(),
        ]);
    }

    public function export(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');
        if (! $subscriptions->hasFeature($business, 'export')) {
            return back()->with('error', 'Tu plan no incluye exportación.');
        }
        \App\Models\AuditLog::record('customers.exported', $business, null, [], $request->user());

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nombre', 'Correo', 'Teléfono', 'Programa', 'Sellos', 'Sellos históricos', 'Ciclo', 'Última actividad', 'Estado']);
            CustomerCard::with(['customer', 'program'])->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    fputcsv($out, array_map([$this, 'safe'], [
                        $c->customer->name, $c->customer->email, $c->customer->phone, $c->program->name, $c->stamps_balance,
                        $c->lifetime_stamps, $c->cycle_number, $c->last_activity_at?->format('Y-m-d H:i'), $c->status,
                    ]));
                }
            });
            fclose($out);
        }, 'clientes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita inyección de fórmulas al abrir el CSV en hojas de cálculo. */
    public function safe($value): string
    {
        $v = (string) $value;

        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    public function adjust(Request $request, CustomerCard $card, LoyaltyService $loyalty)
    {
        $data = $request->validate(['stamps' => ['required', 'integer', 'not_in:0', 'between:-50,50'], 'reason' => ['required', 'string', 'max:200']]);
        try {
            $loyalty->adjust($card, $request->user(), (int) $data['stamps'], $data['reason']);
        } catch (LoyaltyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Corrección registrada.');
    }

    public function reverse(Request $request, StampTransaction $transaction, LoyaltyService $loyalty)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        try {
            $loyalty->reverse($transaction, $request->user(), $data['reason']);
        } catch (LoyaltyException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Movimiento revertido.');
    }
}

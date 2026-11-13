<?php

namespace App\Services;

use App\Models\Business;
use App\Models\CustomerCard;
use App\Models\RewardEntitlement;
use App\Models\RewardRedemption;
use App\Models\StampTransaction;
use App\Models\WalletPass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Métricas del panel. Todas las consultas están acotadas al negocio recibido. */
class StatsService
{
    public function range(?string $from, ?string $to): array
    {
        try {
            $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
            $start = $from ? CarbonImmutable::parse($from)->startOfDay() : $end->subDays(29)->startOfDay();
        } catch (\Throwable) {
            $end = CarbonImmutable::now()->endOfDay();
            $start = $end->subDays(29)->startOfDay();
        }
        if ($start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }
        if ($start->diffInDays($end) > 366) {
            $start = $end->subDays(366)->startOfDay();
        }

        return [$start, $end];
    }

    public function kpis(Business $b, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $earn = StampTransaction::withoutGlobalScopes()->where('business_id', $b->id)->where('type', 'earn')->whereBetween('created_at', [$from, $to]);
        $cards = CustomerCard::withoutGlobalScopes()->where('business_id', $b->id);
        $totalCustomers = DB::table('customer_business')->where('business_id', $b->id)->count();
        $activeCards = (clone $cards)->where('status', 'active')->count();
        $cardsWithReward = RewardEntitlement::withoutGlobalScopes()->where('business_id', $b->id)->distinct('card_id')->count('card_id');
        $redemptions = RewardRedemption::withoutGlobalScopes()->where('business_id', $b->id)->whereBetween('redeemed_at', [$from, $to])->count();
        $passes = WalletPass::query()->whereIn('card_id', (clone $cards)->select('id'));

        return [
            'Clientes registrados' => $totalCustomers,
            'Clientes nuevos (período)' => DB::table('customer_business')->where('business_id', $b->id)->whereBetween('created_at', [$from, $to])->count(),
            'Tarjetas activas' => $activeCards,
            'Sellos otorgados (período)' => (int) (clone $earn)->sum('stamps'),
            'Sellos hoy' => (int) StampTransaction::withoutGlobalScopes()->where('business_id', $b->id)->where('type', 'earn')->whereDate('created_at', now()->toDateString())->sum('stamps'),
            'Operaciones (período)' => (clone $earn)->count(),
            'Recompensas disponibles' => RewardEntitlement::withoutGlobalScopes()->where('business_id', $b->id)->where('status', 'available')->count(),
            'Recompensas canjeadas (período)' => $redemptions,
            'Conversión a recompensa' => $totalCustomers ? round($cardsWithReward / max(1, $activeCards + (clone $cards)->where('status', '!=', 'active')->count()) * 100).'%' : '0%',
            'Promedio de sellos por tarjeta' => $activeCards ? round((float) (clone $cards)->where('status', 'active')->avg('stamps_balance'), 1) : 0,
            'Clientes inactivos (30 días)' => (clone $cards)->where('status', 'active')->where(fn ($q) => $q->whereNull('last_activity_at')->orWhere('last_activity_at', '<', now()->subDays(30)))->count(),
            'Programas activos' => $b->programs()->where('status', 'active')->count(),
            'Empleados activos' => DB::table('business_user')->where('business_id', $b->id)->where('is_active', true)->count(),
            'Pases Wallet verificados' => (clone $passes)->where('status', 'installed_verified')->count(),
            'Pases Wallet generados' => (clone $passes)->count(),
            'Revertidos (período)' => StampTransaction::withoutGlobalScopes()->where('business_id', $b->id)->where('type', 'reverse')->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    /** Serie diaria de sellos y clientes nuevos para la gráfica. */
    public function series(Business $b, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $stamps = StampTransaction::withoutGlobalScopes()->where('business_id', $b->id)->where('type', 'earn')->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) d, SUM(stamps) s')->groupBy('d')->pluck('s', 'd');
        $customers = DB::table('customer_business')->where('business_id', $b->id)->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) d, COUNT(*) c')->groupBy('d')->pluck('c', 'd');

        $labels = $s = $c = [];
        for ($day = $from->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $s[] = (int) ($stamps[$key] ?? 0);
            $c[] = (int) ($customers[$key] ?? 0);
        }

        return ['labels' => $labels, 'stamps' => $s, 'customers' => $c];
    }

    public function topEmployees(Business $b, CarbonImmutable $from, CarbonImmutable $to, int $limit = 5)
    {
        return StampTransaction::withoutGlobalScopes()->from('stamp_transactions as t')->join('users as u', 'u.id', '=', 't.user_id')
            ->where('t.business_id', $b->id)->where('t.type', 'earn')->whereBetween('t.created_at', [$from, $to])
            ->selectRaw('u.name, COUNT(*) ops, SUM(t.stamps) stamps')->groupBy('u.id', 'u.name')->orderByDesc('stamps')->limit($limit)->get();
    }

    public function topBranches(Business $b, CarbonImmutable $from, CarbonImmutable $to, int $limit = 5)
    {
        return StampTransaction::withoutGlobalScopes()->from('stamp_transactions as t')->join('business_branches as br', 'br.id', '=', 't.branch_id')
            ->where('t.business_id', $b->id)->where('t.type', 'earn')->whereBetween('t.created_at', [$from, $to])
            ->selectRaw('br.name, COUNT(*) ops, SUM(t.stamps) stamps')->groupBy('br.id', 'br.name')->orderByDesc('stamps')->limit($limit)->get();
    }
}

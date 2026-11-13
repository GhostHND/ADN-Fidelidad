<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\CustomerCard;
use App\Models\RewardRedemption;
use App\Models\StampTransaction;
use App\Services\StatsService;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to] = app(StatsService::class)->range($request->query('from'), $request->query('to'));
        $type = $request->query('type');

        $transactions = StampTransaction::with(['customer', 'employee', 'branch', 'program'])
            ->whereBetween('created_at', [$from, $to])
            ->when(in_array($type, ['earn', 'reverse', 'adjust', 'consume'], true), fn ($q) => $q->where('type', $type))
            ->when($request->query('user'), fn ($q, $u) => $q->where('user_id', (int) $u))
            ->latest('id')->paginate(30)->withQueryString();

        return view('business.history', [
            'transactions' => $transactions,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'staff' => $request->attributes->get('business')->users()->orderBy('name')->get(),
            'redemptions' => RewardRedemption::with(['entitlement.reward', 'entitlement.card.customer', 'employee'])->whereBetween('redeemed_at', [$from, $to])->latest('id')->limit(15)->get(),
        ]);
    }

    public function reports(Request $request, StatsService $stats)
    {
        $business = $request->attributes->get('business');
        [$from, $to] = $stats->range($request->query('from'), $request->query('to'));
        if (! app(\App\Services\SubscriptionService::class)->hasFeature($business, 'reports')) {
            return view('business.reports', ['locked' => true, 'from' => $from->toDateString(), 'to' => $to->toDateString()]);
        }

        return view('business.reports', [
            'locked' => false,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'kpis' => $stats->kpis($business, $from, $to),
            'employees' => $stats->topEmployees($business, $from, $to, 10),
            'branches' => $stats->topBranches($business, $from, $to, 10),
            'top' => CustomerCard::with('customer')->orderByDesc('lifetime_stamps')->limit(10)->get(),
        ]);
    }
}

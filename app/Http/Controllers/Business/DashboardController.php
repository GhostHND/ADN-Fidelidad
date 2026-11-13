<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\StampTransaction;
use App\Services\StatsService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, StatsService $stats)
    {
        $business = $request->attributes->get('business');
        [$from, $to] = $stats->range($request->query('from'), $request->query('to'));

        return view('business.dashboard', [
            'kpis' => $stats->kpis($business, $from, $to),
            'series' => $stats->series($business, $from, $to),
            'employees' => $stats->topEmployees($business, $from, $to),
            'branches' => $stats->topBranches($business, $from, $to),
            'recent' => StampTransaction::with(['customer', 'program'])->latest()->limit(8)->get(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function switch(Request $request)
    {
        $id = (int) $request->input('business_id');
        abort_unless($request->user()->businesses()->wherePivot('is_active', true)->whereKey($id)->exists(), 403);
        $request->session()->put('business_id', $id);

        return redirect()->route('app.dashboard');
    }

    public function subscription(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');

        return view('business.subscription', ['summary' => $subscriptions->summary($business)]);
    }
}

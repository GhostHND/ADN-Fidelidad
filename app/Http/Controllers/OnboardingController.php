<?php

namespace App\Http\Controllers;

use App\Services\OnboardingGuides;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    public function store(Request $request, string $guide, OnboardingGuides $guides): JsonResponse
    {
        $admin = $request->routeIs('admin.onboarding.store');
        $business = $admin ? null : $request->attributes->get('business');
        abort_unless($guides->allowed($guide, $request->user(), $business, $admin, $request->session()->has('impersonate_business_id')), 404);
        DB::table('onboarding_progress')->upsert([[
            'user_id' => $request->user()->id,
            'business_id' => $business?->id,
            'scope_key' => $business ? 'business-'.$business->id : 'admin',
            'guide' => $guide,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['user_id', 'scope_key', 'guide'], ['updated_at']);

        return response()->json(['ok' => true]);
    }
}

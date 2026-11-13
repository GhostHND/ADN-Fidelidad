<?php

namespace App\View\Components;

use App\Services\OnboardingGuides;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

class FirstUseGuide extends Component
{
    public ?array $guide;

    public bool $seen = false;

    public string $saveUrl = '';

    public function __construct(OnboardingGuides $guides)
    {
        $request = request();
        $admin = $request->is('admin*');
        $business = $admin ? null : $request->attributes->get('business');
        $this->guide = $guides->forRoute($request->route()?->getName(), $request->user(), $business, $admin, $request->session()->has('impersonate_business_id'));
        if ($this->guide) {
            $this->seen = DB::table('onboarding_progress')
                ->where('user_id', $request->user()->id)
                ->where('scope_key', $business ? 'business-'.$business->id : 'admin')
                ->where('guide', $this->guide['key'])->exists();
            $this->saveUrl = route($admin ? 'admin.onboarding.store' : 'app.onboarding.store', $this->guide['key']);
        }
    }

    public function render(): View
    {
        return view('components.first-use-guide');
    }
}

<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BusinessBranch;
use App\Services\MediaService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessController extends Controller
{
    public function edit(Request $request)
    {
        return view('business.profile', ['business' => $request->attributes->get('business'), 'branches' => BusinessBranch::orderBy('name')->get()]);
    }

    public function update(Request $request, MediaService $media)
    {
        $business = $request->attributes->get('business');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'privacy_notice' => ['nullable', 'string', 'max:5000'],
            'program_terms' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'file', 'max:'.config('fidelidad.uploads.max_kb')],
        ]);

        if ($request->hasFile('logo')) {
            try {
                $asset = $media->store($business, $request->file('logo'), 'logo');
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['logo' => $e->getMessage()])->withInput();
            }
            if ($business->logo_path) {
                Storage::disk('public')->delete($business->logo_path);
            }
            $data['logo_path'] = $asset->path;
        }
        unset($data['logo']);

        $business->update($data);
        AuditLog::record('business.updated', $business, $business, ['fields' => array_keys($data)], $request->user());

        return back()->with('status', 'Datos del negocio guardados.');
    }

    public function storeBranch(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');
        if (! $subscriptions->withinLimit($business, 'max_branches', BusinessBranch::count())) {
            return back()->with('error', 'Tu plan alcanzó el límite de sucursales.');
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:40']]);
        $branch = BusinessBranch::create($data);
        AuditLog::record('branch.created', $business, $branch, [], $request->user());

        return back()->with('status', 'Sucursal creada.');
    }

    public function updateBranch(Request $request, BusinessBranch $branch)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'address' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:40'], 'is_active' => ['nullable', 'boolean']]);
        $data['is_active'] = $request->boolean('is_active');
        $branch->update($data);
        AuditLog::record('branch.updated', $request->attributes->get('business'), $branch, [], $request->user());

        return back()->with('status', 'Sucursal actualizada.');
    }
}

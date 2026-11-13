<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BusinessBranch;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function index(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');

        return view('business.staff', [
            'staff' => $business->users()->orderBy('name')->get(),
            'branches' => BusinessBranch::where('is_active', true)->orderBy('name')->get(),
            'canAdd' => $subscriptions->withinLimit($business, 'max_staff', $business->users()->count()),
            'limit' => $subscriptions->limit($business, 'max_staff'),
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');
        if (! $subscriptions->withinLimit($business, 'max_staff', $business->users()->count())) {
            return back()->with('error', 'Tu plan alcanzó el límite de empleados.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', Password::min(10)],
            'role' => ['required', Rule::in(array_keys(config('fidelidad.roles')))],
            'branch_id' => ['nullable', 'integer'],
        ]);
        $branchId = $this->branch($data['branch_id'] ?? null);

        $user = User::where('email', $data['email'])->first();
        if ($user && $user->is_superadmin) {
            return back()->with('error', 'Ese correo no puede asignarse como empleado.');
        }
        if ($user && $business->users()->whereKey($user->id)->exists()) {
            return back()->with('error', 'Ese usuario ya pertenece a tu negocio.');
        }

        DB::transaction(function () use (&$user, $data, $business, $branchId) {
            $user ??= User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'is_active' => true]);
            $business->users()->attach($user->id, ['role' => $data['role'], 'branch_id' => $branchId, 'is_active' => true]);
        });
        AuditLog::record('staff.created', $business, $user, ['role' => $data['role']], $request->user());

        return back()->with('status', 'Empleado agregado.');
    }

    public function update(Request $request, User $user)
    {
        $business = $request->attributes->get('business');
        abort_unless($business->users()->whereKey($user->id)->exists(), 404);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(config('fidelidad.roles')))],
            'branch_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(config('fidelidad.permissions')))],
            'password' => ['nullable', Password::min(10)],
        ]);
        $active = $request->boolean('is_active');

        $isSelf = $user->id === $request->user()->id;
        $current = $business->users()->whereKey($user->id)->first()->pivot;
        $losingOwner = $current->role === 'owner' && ($data['role'] !== 'owner' || ! $active);
        if ($losingOwner && ($isSelf || $business->users()->wherePivot('role', 'owner')->wherePivot('is_active', true)->count() <= 1)) {
            return back()->with('error', 'Debe quedar al menos un propietario activo y no puedes quitarte tu propio acceso.');
        }

        $business->users()->updateExistingPivot($user->id, [
            'role' => $data['role'],
            'branch_id' => $this->branch($data['branch_id'] ?? null),
            'is_active' => $active,
            'permissions' => $request->has('custom_permissions') ? json_encode(array_values($data['permissions'] ?? [])) : null,
        ]);
        if (! empty($data['password'])) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }
        AuditLog::record('staff.updated', $business, $user, ['role' => $data['role'], 'active' => $active], $request->user());

        return back()->with('status', 'Empleado actualizado.');
    }

    private function branch(?int $id): ?int
    {
        if (! $id) {
            return null;
        }
        abort_unless(BusinessBranch::whereKey($id)->exists(), 422, 'Sucursal inválida.');

        return $id;
    }
}

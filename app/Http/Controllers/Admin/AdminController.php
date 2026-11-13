<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\CustomerCard;
use App\Models\MasterSyncEvent;
use App\Models\StampTransaction;
use App\Models\SubscriptionEntitlement;
use App\Models\User;
use App\Models\WalletSyncJob;
use App\Services\Master\MasterSyncService;
use App\Services\SubscriptionService;
use App\Services\Wallet\WalletManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const STATUSES = ['active', 'trial', 'past_due', 'suspended', 'expired', 'cancelled'];

    public function dashboard(SubscriptionService $subs)
    {
        $businesses = Business::with('entitlement')->get();

        return view('admin.dashboard', [
            'total' => $businesses->count(),
            'byMode' => $businesses->groupBy(fn ($b) => $subs->mode($b))->map->count(),
            'cards' => CustomerCard::withoutGlobalScopes()->count(),
            'stampsToday' => (int) StampTransaction::withoutGlobalScopes()->where('type', 'earn')->whereDate('created_at', now()->toDateString())->sum('stamps'),
            'failedJobs' => WalletSyncJob::withoutGlobalScopes()->where('status', 'failed')->count(),
            'failedEvents' => MasterSyncEvent::whereIn('status', ['failed', 'rejected'])->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    public function businesses(Request $request, SubscriptionService $subs)
    {
        $businesses = Business::with('entitlement')->withCount('users')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$s.'%')->orWhere('master_business_id', 'like', '%'.$s.'%')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.businesses', ['businesses' => $businesses, 'subs' => $subs]);
    }

    public function createBusiness()
    {
        return view('admin.business-create', ['features' => config('fidelidad.plan_features'), 'statuses' => self::STATUSES]);
    }

    public function storeBusiness(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'master_business_id' => ['nullable', 'string', 'max:100', 'unique:businesses,master_business_id'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_password' => ['required', 'string', 'min:10', 'max:100'],
            'plan' => ['nullable', 'string', 'max:60'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'ends_at' => ['nullable', 'date'],
            'features' => ['array'],
            'features.*' => [Rule::in(config('fidelidad.plan_features'))],
        ]);

        $business = DB::transaction(function () use ($data, $request) {
            $owner = User::where('email', $data['owner_email'])->first()
                ?? User::create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => Hash::make($data['owner_password']), 'is_active' => true]);
            $business = Business::create(['name' => $data['name'], 'master_business_id' => $data['master_business_id'] ?? null]);
            $business->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true]);
            // Autorización manual: queda marcada con event_version 0 para que el primer evento de Master la sustituya.
            SubscriptionEntitlement::create([
                'business_id' => $business->id, 'plan' => $data['plan'] ?? 'manual', 'status' => $data['status'],
                'ends_at' => $data['ends_at'] ?? null, 'features' => $data['features'] ?? [], 'limits' => config('fidelidad.limits_default'),
                'event_version' => 0, 'subscription_id' => 'manual',
            ]);
            AuditLog::record('admin.business_created', $business, $business, ['owner' => $owner->email], $request->user());

            return $business;
        });

        return redirect()->route('admin.businesses.show', $business)->with('status', 'Negocio creado.');
    }

    public function showBusiness(Business $business, SubscriptionService $subs)
    {
        return view('admin.business-show', [
            'business' => $business->load(['entitlement', 'users']),
            'summary' => $subs->summary($business),
            'events' => MasterSyncEvent::where('master_business_id', $business->master_business_id)->latest('id')->limit(10)->get(),
            'features' => config('fidelidad.plan_features'),
            'statuses' => self::STATUSES,
        ]);
    }

    public function updateBusiness(Request $request, Business $business)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'master_business_id' => ['nullable', 'string', 'max:100', Rule::unique('businesses', 'master_business_id')->ignore($business->id)],
        ]);
        $business->update($data);
        AuditLog::record('admin.business_updated', $business, $business, $data, $request->user());

        return back()->with('status', 'Negocio actualizado.');
    }

    public function pullBusiness(Request $request, Business $business, MasterSyncService $master)
    {
        $result = $master->pull($business);
        AuditLog::record('admin.master_pull', $business, $business, ['result' => $result['status']], $request->user());

        return back()->with($result['http'] < 300 ? 'status' : 'error', $result['message']);
    }

    public function impersonate(Request $request, Business $business)
    {
        $request->session()->put('impersonate_business_id', $business->id);
        AuditLog::record('admin.impersonate_start', $business, $business, [], $request->user());

        return redirect()->route('app.dashboard');
    }

    public function leave(Request $request)
    {
        $request->session()->forget(['impersonate_business_id', 'business_id']);

        return redirect()->route('admin.dashboard');
    }

    public function users(Request $request)
    {
        return view('admin.users', [
            'users' => User::with('businesses:id,name')->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$s.'%')->orWhere('email', 'like', '%'.$s.'%')))->orderBy('name')->paginate(25)->withQueryString(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10', 'max:100'],
        ]);
        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true]);
        $user->is_superadmin = true;
        $user->save();
        AuditLog::record('admin.superadmin_created', null, $user, ['email' => $user->email], $request->user());

        return back()->with('status', 'Superadministrador creado.');
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'password' => ['nullable', 'string', 'min:10', 'max:100']]);
        abort_if($user->id === $request->user()->id && ! $data['is_active'], 422, 'No puedes desactivarte a ti mismo.');
        $user->is_active = (bool) $data['is_active'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        AuditLog::record('admin.user_updated', null, $user, ['is_active' => $user->is_active], $request->user());

        return back()->with('status', 'Usuario actualizado.');
    }

    public function wallet(Request $request, WalletManager $wallet)
    {
        return view('admin.wallet', [
            'jobs' => WalletSyncJob::withoutGlobalScopes()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->latest('id')->paginate(30)->withQueryString(),
            'providers' => collect($wallet->providers())->map(fn ($p) => ['ok' => $p->isConfigured(), 'missing' => $p->missingConfig()]),
        ]);
    }

    public function processWallet(WalletManager $wallet)
    {
        [$done, $failed, $skipped] = $wallet->processDue();

        return back()->with('status', "Procesados: {$done} · fallidos: {$failed} · omitidos: {$skipped}");
    }

    public function retryJob(WalletSyncJob $job)
    {
        $job->update(['status' => 'pending', 'attempts' => 0, 'next_attempt_at' => now(), 'last_error' => null]);

        return back()->with('status', 'Trabajo reprogramado.');
    }

    public function events()
    {
        return view('admin.events', ['events' => MasterSyncEvent::latest('id')->paginate(30)]);
    }

    public function audit(Request $request)
    {
        return view('admin.audit', ['logs' => AuditLog::with('user:id,name')->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))->latest('id')->paginate(40)->withQueryString()]);
    }

    public function system(WalletManager $wallet)
    {
        return view('admin.system', [
            'checks' => [
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Entorno' => app()->environment(),
                'APP_DEBUG' => config('app.debug') ? 'ACTIVO (desactívalo en producción)' : 'desactivado',
                'Base de datos' => config('database.default'),
                'Cola' => config('queue.default'),
                'Software Master' => config('fidelidad.master.api_url') && config('fidelidad.master.api_secret') ? 'configurado' : 'sin configurar',
                'Webhook secret' => config('fidelidad.master.webhook_secret') ? 'configurado' : 'sin configurar',
            ],
            'providers' => collect($wallet->providers())->map(fn ($p) => ['ok' => $p->isConfigured(), 'missing' => $p->missingConfig()]),
            'pendingJobs' => WalletSyncJob::withoutGlobalScopes()->where('status', 'pending')->count(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoyaltyProgram;
use App\Models\MediaAsset;
use App\Models\ProgramVersion;
use App\Models\Reward;
use App\Models\StampRule;
use App\Services\SubscriptionService;
use App\Services\Wallet\WalletSyncQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    public function index()
    {
        return view('business.programs.index', ['programs' => LoyaltyProgram::withCount(['cards', 'rewards'])->latest()->get()]);
    }

    public function create(Request $request)
    {
        $business = $request->attributes->get('business');

        return view('business.programs.edit', [
            'program' => new LoyaltyProgram([
                'name' => '', 'stamps_goal' => 8, 'rule_type' => 'per_purchase', 'max_stamps_per_operation' => 5, 'cycle_policy' => 'reset', 'status' => 'draft',
                'design' => ['background_color' => $business->secondary_color, 'foreground_color' => '#FFFFFF', 'label_color' => $business->primary_color, 'card_title' => $business->name],
            ]),
            'assets' => MediaAsset::whereIn('kind', ['stamp_on', 'stamp_off'])->get(),
            'business' => $business,
        ]);
    }

    public function store(Request $request, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');
        $count = LoyaltyProgram::where('status', '!=', 'archived')->count();
        if (! $subscriptions->withinLimit($business, 'max_programs', $count)
            || ($count >= 1 && ! $subscriptions->hasFeature($business, 'multiple_programs'))) {
            return back()->with('error', 'Tu plan no permite crear más programas de fidelidad.')->withInput();
        }

        $data = $this->validated($request);
        $program = DB::transaction(function () use ($data, $request) {
            $program = LoyaltyProgram::create($data + ['status' => 'draft', 'current_version' => 1]);
            $this->snapshot($program, $request, 'Versión inicial');

            return $program;
        });
        AuditLog::record('program.created', $business, $program, [], $request->user());

        return redirect()->route('app.programs.edit', $program)->with('status', 'Tarjeta creada como borrador. Agrega al menos una recompensa y actívala.');
    }

    public function edit(Request $request, LoyaltyProgram $program)
    {
        return view('business.programs.edit', [
            'program' => $program->load(['rewards', 'stampRules', 'versions' => fn ($q) => $q->latest('version')]),
            'assets' => MediaAsset::whereIn('kind', ['stamp_on', 'stamp_off'])->get(),
            'business' => $request->attributes->get('business'),
        ]);
    }

    public function update(Request $request, LoyaltyProgram $program)
    {
        $business = $request->attributes->get('business');
        $data = $this->validated($request);
        $before = $program->snapshot();
        $data['design'] = array_replace($program->design ?? [], $data['design']);
        $program->fill($data);

        $changed = collect(LoyaltyProgram::VERSIONED)->filter(fn ($k) => json_encode($program->{$k}) !== json_encode($before[$k]))->values()->all();
        DB::transaction(function () use ($program, $changed, $request) {
            if ($changed) {
                $program->current_version++;
            }
            $program->save();
            if ($changed) {
                $this->snapshot($program, $request, $request->input('change_note') ?: 'Cambios: '.implode(', ', $changed));
            }
        });

        $queued = 0;
        if ($changed && $program->status === 'active') {
            $queued = app(WalletSyncQueue::class)->queueProgram($program, 'program_updated');
        }
        AuditLog::record('program.updated', $business, $program, ['changed' => $changed], $request->user());

        $msg = $changed ? 'Tarjeta guardada (versión '.$program->current_version.').' : 'No hubo cambios que guardar.';
        if ($queued) {
            $msg .= " Se programó la actualización de {$queued} tarjetas en Wallet.";
        }

        return back()->with('status', $msg);
    }

    public function status(Request $request, LoyaltyProgram $program)
    {
        $to = $request->validate(['status' => ['required', Rule::in(['active', 'paused', 'archived', 'draft'])]])['status'];
        if ($to === 'active' && ! $program->rewards()->where('status', 'active')->exists()) {
            return back()->with('error', 'Agrega al menos una recompensa activa antes de activar la tarjeta.');
        }
        $program->update(['status' => $to, 'published_at' => $to === 'active' ? ($program->published_at ?? now()) : $program->published_at]);
        AuditLog::record('program.status', $request->attributes->get('business'), $program, ['status' => $to], $request->user());

        return back()->with('status', 'Estado actualizado.');
    }

    public function duplicate(Request $request, LoyaltyProgram $program, SubscriptionService $subscriptions)
    {
        $business = $request->attributes->get('business');
        $count = LoyaltyProgram::where('status', '!=', 'archived')->count();
        if (! $subscriptions->withinLimit($business, 'max_programs', $count) || ! $subscriptions->hasFeature($business, 'multiple_programs')) {
            return back()->with('error', 'Tu plan no permite crear más programas de fidelidad.');
        }
        $copy = DB::transaction(function () use ($program, $request) {
            $copy = LoyaltyProgram::create($program->only(['name', 'description', 'stamps_goal', 'rule_type', 'amount_per_stamp', 'max_stamps_per_operation', 'cycle_policy', 'terms', 'design'])
                + ['status' => 'draft', 'current_version' => 1, 'name' => Str::limit($program->name, 100, '').' (copia)']);
            foreach ($program->rewards as $r) {
                Reward::create($r->only(['name', 'description', 'type', 'value', 'stamps_required', 'consumes_stamps', 'terms', 'image_asset_id']) + ['program_id' => $copy->id, 'status' => 'active']);
            }
            foreach ($program->stampRules as $r) {
                StampRule::create($r->only(['name', 'type', 'stamps']) + ['program_id' => $copy->id]);
            }
            $this->snapshot($copy, $request, 'Duplicada de '.$program->name);

            return $copy;
        });

        return redirect()->route('app.programs.edit', $copy)->with('status', 'Tarjeta duplicada como borrador.');
    }

    public function storeReward(Request $request, LoyaltyProgram $program)
    {
        $reward = Reward::create($this->rewardData($request) + ['program_id' => $program->id]);
        AuditLog::record('reward.created', $request->attributes->get('business'), $reward, [], $request->user());

        return back()->with('status', 'Recompensa creada.');
    }

    public function updateReward(Request $request, Reward $reward)
    {
        $reward->update($this->rewardData($request));
        AuditLog::record('reward.updated', $request->attributes->get('business'), $reward, [], $request->user());

        return back()->with('status', 'Recompensa actualizada.');
    }

    public function storeRule(Request $request, LoyaltyProgram $program)
    {
        StampRule::create($this->ruleData($request) + ['program_id' => $program->id]);

        return back()->with('status', 'Regla creada.');
    }

    public function updateRule(Request $request, StampRule $rule)
    {
        abort_unless(LoyaltyProgram::whereKey($rule->program_id)->exists(), 404);
        $rule->update($this->ruleData($request));

        return back()->with('status', 'Regla actualizada.');
    }

    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stamps_goal' => ['required', 'integer', 'between:1,50'],
            'rule_type' => ['required', Rule::in(['per_purchase', 'per_amount', 'per_service', 'promotional'])],
            'amount_per_stamp' => ['nullable', 'numeric', 'min:0.01', 'max:10000000', 'required_if:rule_type,per_amount'],
            'max_stamps_per_operation' => ['required', 'integer', 'between:1,50'],
            'cycle_policy' => ['required', Rule::in(['reset', 'continue', 'keep_remainder'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'terms' => ['nullable', 'string', 'max:3000'],
            'design.background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.foreground_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.label_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'design.card_title' => ['nullable', 'string', 'max:40'],
            'design.stamp_on_asset_id' => ['nullable', 'integer'],
            'design.stamp_off_asset_id' => ['nullable', 'integer'],
        ]);
        foreach (['stamp_on_asset_id', 'stamp_off_asset_id'] as $k) {
            $id = $v['design'][$k] ?? null;
            if ($id && ! MediaAsset::whereKey($id)->exists()) {
                abort(422, 'Recurso de imagen inválido.');
            }
            $v['design'][$k] = $id ?: null;
        }

        return $v;
    }

    private function snapshot(LoyaltyProgram $program, Request $request, string $note): void
    {
        ProgramVersion::create([
            'program_id' => $program->id,
            'version' => $program->current_version,
            'snapshot' => $program->snapshot(),
            'created_by' => $request->user()->id,
            'change_note' => Str::limit($note, 250, ''),
            'platform_notes' => [
                'apple' => 'Los cambios de diseño se aplican al actualizar el pase; iOS puede tardar en mostrarlos.',
                'google' => 'La clase y los objetos se actualizan por API; la visualización depende de Google Wallet.',
            ],
        ]);
    }

    private function rewardData(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(array_keys(trans('fidelidad.reward_type')))],
            'value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'stamps_required' => ['required', 'integer', 'between:1,500'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'valid_until' => ['nullable', 'date'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ]);
        $v['consumes_stamps'] = $request->boolean('consumes_stamps');

        return $v;
    }

    private function ruleData(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['service', 'promotional'])],
            'stamps' => ['required', 'integer', 'between:1,50'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);
        $v['is_active'] = $request->boolean('is_active', true);

        return $v;
    }
}

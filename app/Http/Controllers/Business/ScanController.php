<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessBranch;
use App\Models\CustomerCard;
use App\Models\RewardEntitlement;
use App\Models\StampRule;
use App\Services\LoyaltyException;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->attributes->get('business');
        $membership = $request->user()->membership($business);

        return view('business.scan', [
            'branches' => BusinessBranch::where('is_active', true)->orderBy('name')->get(),
            'fixedBranch' => $membership?->branch_id,
        ]);
    }

    public function lookup(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:200']]);
        try {
            $card = $loyalty->findCardByQr($request->attributes->get('business'), $data['code']);
        } catch (LoyaltyException $e) {
            return $this->fail($e);
        }

        return response()->json(['ok' => true, 'card' => $this->payload($card)]);
    }

    public function award(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $business = $request->attributes->get('business');
        $data = $request->validate([
            'card' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
            'branch_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'rule_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $card = CustomerCard::where('uuid', $data['card'])->firstOrFail();
            $result = $loyalty->award(
                $card, $request->user(), $this->branch($request, $data['branch_id'] ?? null), $data,
                'op-'.$business->id.'-'.$data['idempotency_key']
            );
        } catch (LoyaltyException $e) {
            return $this->fail($e);
        }

        $tx = $result['transaction'];
        $message = $result['duplicate']
            ? 'Esta operación ya estaba registrada; no se duplicaron sellos.'
            : "Se asignaron {$tx->stamps} sello(s).".($result['new_entitlements'] ? ' ¡El cliente ganó una recompensa!' : '');

        return response()->json([
            'ok' => true,
            'message' => $message,
            'duplicate' => $result['duplicate'],
            'new_entitlements' => $result['new_entitlements'],
            'cycle_completed' => ! $result['duplicate'] && $tx->cycle_number < $result['card']->cycle_number,
            'card' => $this->payload($result['card']),
        ]);
    }

    public function redeem(Request $request, LoyaltyService $loyalty): JsonResponse
    {
        $data = $request->validate(['entitlement' => ['required', 'uuid'], 'branch_id' => ['nullable', 'integer']]);
        try {
            $ent = RewardEntitlement::with('reward')->where('uuid', $data['entitlement'])->firstOrFail();
            $loyalty->redeem($ent, $request->user(), $this->branch($request, $data['branch_id'] ?? null));
        } catch (LoyaltyException $e) {
            return $this->fail($e);
        }
        $card = CustomerCard::findOrFail($ent->card_id);

        return response()->json(['ok' => true, 'message' => 'Recompensa canjeada: '.$ent->reward->name, 'card' => $this->payload($card)]);
    }

    private function branch(Request $request, ?int $requested): ?int
    {
        $membership = $request->user()->membership($request->attributes->get('business'));
        if ($membership?->branch_id) {
            return (int) $membership->branch_id;
        }

        return $requested ?: null;
    }

    private function fail(LoyaltyException $e): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $e->getMessage()], $e->status);
    }

    private function payload(CustomerCard $card): array
    {
        $card->load(['customer', 'program']);
        $program = $card->program;

        return [
            'token' => $card->uuid,
            'customer' => $card->customer->name,
            'program' => $program->name,
            'stamps' => $card->stamps_balance,
            'goal' => $program->stamps_goal,
            'status' => $card->status,
            'rule_type' => $program->rule_type,
            'amount_per_stamp' => $program->amount_per_stamp,
            'max' => $program->max_stamps_per_operation,
            'rules' => StampRule::where('program_id', $program->id)->where('is_active', true)->get()->filter->isCurrent()
                ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'stamps' => $r->stamps])->values(),
            'rewards' => RewardEntitlement::with('reward')->where('card_id', $card->id)->where('status', 'available')->get()
                ->map(fn ($e) => ['id' => $e->uuid, 'name' => $e->reward->name])->values(),
            'open' => $program->isOpen(),
        ];
    }
}

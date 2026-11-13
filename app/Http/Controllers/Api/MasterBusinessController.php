<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterBusinessController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:120', 'not_regex:/[%_\\\\]/'],
        ]);
        $query = trim($data['query']);
        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages(['query' => 'Ingresa al menos dos caracteres.']);
        }

        $businesses = Business::query()
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(20)
            ->get(['uuid', 'name', 'master_business_id']);

        return response()->json(['data' => $businesses]);
    }

    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business_uuid' => ['required', 'uuid'],
            'master_business_id' => ['required', 'uuid'],
        ]);

        try {
            $business = DB::transaction(function () use ($data): Business {
                $business = Business::query()->where('uuid', $data['business_uuid'])->lockForUpdate()->firstOrFail();
                if ($business->master_business_id === $data['master_business_id']) {
                    return $business;
                }
                if ($business->master_business_id !== null) {
                    throw ValidationException::withMessages(['business_uuid' => 'El negocio ya está vinculado a otra instancia.']);
                }
                if ($business->entitlement()->where('event_version', '>', 0)->exists()) {
                    throw ValidationException::withMessages(['business_uuid' => 'El negocio tiene una sincronización anterior; revisa su vinculación antes de continuar.']);
                }
                if (Business::withTrashed()->where('master_business_id', $data['master_business_id'])->exists()) {
                    throw ValidationException::withMessages(['master_business_id' => 'Esta instancia ya está vinculada a otro negocio.']);
                }

                $business->update(['master_business_id' => $data['master_business_id']]);
                AuditLog::record('master.business_linked', $business, $business, ['master_business_id' => $data['master_business_id']]);

                return $business;
            });
        } catch (QueryException $exception) {
            if (Business::withTrashed()->where('master_business_id', $data['master_business_id'])->exists()) {
                throw ValidationException::withMessages(['master_business_id' => 'Esta instancia ya está vinculada a otro negocio.']);
            }
            throw $exception;
        }

        return response()->json(['data' => [
            'uuid' => $business->uuid,
            'name' => $business->name,
            'master_business_id' => $business->master_business_id,
        ]]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Master\MasterSyncService;
use Illuminate\Http\Request;

class MasterEntitlementController extends Controller
{
    public function __invoke(Request $request, MasterSyncService $sync)
    {
        $result = $sync->handle($request->json()->all());

        return response()->json(['ok' => in_array($result['status'], ['applied', 'duplicate', 'ignored'], true)] + $result, $result['http']);
    }
}

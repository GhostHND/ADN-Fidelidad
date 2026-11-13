<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Services\MediaService;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    /** Subida de recursos de diseño (sellos, íconos). Devuelve JSON para el editor visual. */
    public function store(Request $request, MediaService $media)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:'.config('fidelidad.uploads.max_kb')],
            'kind' => ['required', 'in:'.implode(',', MediaService::KINDS)],
        ]);

        try {
            $asset = $media->store($request->attributes->get('business'), $request->file('file'), $request->input('kind'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'id' => $asset->id, 'url' => \Storage::disk('public')->url($asset->path)]);
    }
}

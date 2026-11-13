<?php

namespace App\Services;

use App\Models\Business;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Valida y re-codifica imágenes con GD (elimina metadatos y contenido incrustado) antes de guardarlas. */
class MediaService
{
    public const KINDS = ['logo', 'cover', 'stamp_on', 'stamp_off', 'icon', 'reward'];

    public function store(Business $business, UploadedFile $file, string $kind): MediaAsset
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info['mime'], config('fidelidad.uploads.mimes'), true)) {
            throw new \InvalidArgumentException('Formato de imagen no permitido. Usa PNG, JPG o WebP.');
        }
        [$w, $h] = $info;
        if ($w > 4096 || $h > 4096 || $w < 16 || $h < 16) {
            throw new \InvalidArgumentException('Las dimensiones deben estar entre 16 y 4096 píxeles.');
        }

        $src = match ($info['mime']) {
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $src) {
            throw new \InvalidArgumentException('No se pudo procesar la imagen. Verifica que el archivo no esté dañado.');
        }

        $keepAlpha = $info['mime'] !== 'image/jpeg';
        imagesavealpha($src, true);
        ob_start();
        $keepAlpha ? imagepng($src, null, 6) : imagejpeg($src, null, 88);
        $binary = ob_get_clean();
        imagedestroy($src);

        $ext = $keepAlpha ? 'png' : 'jpg';
        $path = 'business/'.$business->id.'/'.$kind.'-'.Str::random(24).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return MediaAsset::withoutGlobalScopes()->create([
            'business_id' => $business->id,
            'kind' => $kind,
            'path' => $path,
            'mime' => $keepAlpha ? 'image/png' : 'image/jpeg',
            'size' => strlen($binary),
            'width' => $w,
            'height' => $h,
            'original_name' => Str::limit($file->getClientOriginalName(), 120, ''),
        ]);
    }
}

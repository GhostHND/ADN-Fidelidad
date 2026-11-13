<?php

namespace App\Services\Wallet;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;

/** Dibuja la franja de sellos (pendientes/completados) como PNG, usada por la web, Apple y Google. */
class StampStripRenderer
{
    public function render(CardView $view, int $width = 750, int $height = 246): string
    {
        $d = $view->design();
        $goal = $view->goal();
        $shown = $view->shown();

        $img = imagecreatetruecolor($width, $height);
        imagesavealpha($img, true);
        [$r, $g, $b] = $this->rgb($d['background_color'] ?? '#1F2937');
        imagefill($img, 0, 0, imagecolorallocate($img, $r, $g, $b));

        $on = $this->loadAsset($view, $d['stamp_on_asset_id'] ?? null);
        $off = $this->loadAsset($view, $d['stamp_off_asset_id'] ?? null);

        $cols = $goal <= 5 ? $goal : (int) ceil($goal / 2);
        $rows = (int) ceil($goal / $cols);
        $pad = 18;
        $cell = (int) min(($width - $pad * 2) / $cols, ($height - $pad * 2) / $rows);
        $size = (int) ($cell * 0.8);
        $startX = (int) (($width - $cell * $cols) / 2);
        $startY = (int) (($height - $cell * $rows) / 2);
        [$lr, $lg, $lb] = $this->rgb($d['label_color'] ?? '#0FA7B4');
        $accent = imagecolorallocate($img, $lr, $lg, $lb);

        for ($i = 0; $i < $goal; $i++) {
            $cx = $startX + ($i % $cols) * $cell + (int) ($cell / 2);
            $cy = $startY + intdiv($i, $cols) * $cell + (int) ($cell / 2);
            $done = $i < $shown;
            $custom = $done ? $on : $off;
            if ($custom) {
                imagecopyresampled($img, $custom, $cx - (int) ($size / 2), $cy - (int) ($size / 2), 0, 0, $size, $size, imagesx($custom), imagesy($custom));
            } elseif ($done) {
                imagefilledellipse($img, $cx, $cy, $size, $size, $accent);
            } else {
                imagesetthickness($img, 4);
                imageellipse($img, $cx, $cy, $size, $size, $accent);
            }
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    private function loadAsset(CardView $view, $id)
    {
        if (! $id) {
            return null;
        }
        $asset = MediaAsset::withoutGlobalScopes()->where('business_id', $view->card->business_id)->find($id);
        if (! $asset || ! Storage::disk('public')->exists($asset->path)) {
            return null;
        }
        $res = @imagecreatefromstring(Storage::disk('public')->get($asset->path));
        if ($res) {
            imagealphablending($res, true);
            imagesavealpha($res, true);
        }

        return $res ?: null;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [31, 41, 55];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}

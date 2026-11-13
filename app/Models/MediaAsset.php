<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use BelongsToBusiness;

    protected $guarded = ['id'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

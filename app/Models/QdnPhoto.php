<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QdnPhoto extends Model
{
    protected $table = 'qdn_photos';

    protected $guarded = [];

    public const DISK = 'local';

    public function qdn(): BelongsTo
    {
        return $this->belongsTo(Qdn::class);
    }

    public function url(): string
    {
        return route('qdn.photos.show', $this);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FocusMaterial extends Model
{
    protected $table = 'focus_materials';

    protected $fillable = [
        'user_id',
        'name',
        'path',
        'type',
        'screen',
    ];

    /* ─────────────────────────────────────────
       RELATIONSHIPS
    ───────────────────────────────────────── */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
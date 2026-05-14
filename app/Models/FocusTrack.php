<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FocusTrack extends Model
{
    protected $fillable = ['title', 'artist', 'file_path', 'is_active'];

    public function getStreamUrlAttribute()
    {
        return route('focus-mode.music.stream');
    }
}
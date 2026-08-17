<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarShare extends Model
{
    protected $fillable = ['id', 'owner_id', 'recipient_id', 'status', 'can_see_details'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id', 'id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarShareRequest extends Model
{
    protected $fillable = ['id', 'requester_id', 'recipient_id', 'message', 'status'];
    protected $keyType = 'string';
    public $incrementing = false;

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id', 'id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id', 'id');
    }
}

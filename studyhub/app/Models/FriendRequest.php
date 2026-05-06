<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FriendRequest extends Model
{
    use HasUuids;

    protected $table = 'friend_requests';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = true;
    
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function scopeBetween($query, string $userA, string $userB)
    {
        return $query->where(function ($inner) use ($userA, $userB) {
            $inner->where('sender_id', $userA)->where('receiver_id', $userB)
                ->orWhere(function ($nested) use ($userA, $userB) {
                    $nested->where('sender_id', $userB)->where('receiver_id', $userA);
                });
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    use HasUuids;

    protected $table    = 'friends';
    protected $keyType  = 'string';
    public $incrementing = false;
    public $timestamps = false;

    // NOTE: 'friends' table has no accepted_at column per schema.
    // Columns: id, user_id, friend_id, created_at
    protected $fillable = [
        'user_id',
        'friend_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friend()
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function scopeBetween($query, string $userA, string $userB)
    {
        return $query->where(function ($inner) use ($userA, $userB) {
            $inner->where('user_id', $userA)->where('friend_id', $userB)
                ->orWhere(function ($nested) use ($userA, $userB) {
                    $nested->where('user_id', $userB)->where('friend_id', $userA);
                });
        });
    }

    public static function areFriends(string $userA, string $userB): bool
    {
        return static::between($userA, $userB)->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    use HasUuids;

    protected $table     = 'friends';
    protected $keyType   = 'string';
    public $incrementing = false;
    public $timestamps   = false;

    protected $fillable = [
        'user_id',
        'friend_id',
    ];

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
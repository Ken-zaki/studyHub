<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GroupMessage extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'group_messages';

    protected $fillable = [
        'id',
        'group_id',
        'user_id',
        'message',
    ];

    public function sender()
    {
        return $this->belongsTo(Profile::class, 'user_id');
    }

    public function attachments()
    {
        return $this->hasMany(GroupMessageAttachment::class, 'message_id');
    }
}

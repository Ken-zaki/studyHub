<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class GroupMessageAttachment extends Model
{
    use HasUuids;

    protected $table = 'group_message_attachments';

    protected $fillable = [
        'id',
        'message_id',
        'file_name',
        'file_url',
        'file_size',
        'attachment_type',
        'storage_path',
    ];

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'message_id');
    }
}

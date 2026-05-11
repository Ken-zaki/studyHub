<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudyGroup extends Model
{
    use HasUuids;

    protected $table = 'study_groups';

    protected $fillable = [
        'id',
        'name',
        'description',
        'subject',
        'is_public',
        'created_by',
    ];

    public function members()
    {
        return $this->hasMany(StudyGroupMember::class, 'group_id');
    }

    public function messages()
    {
        return $this->hasMany(GroupMessage::class, 'group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StudyGroupMember extends Model
{
    use HasUuids;

    protected $table = 'study_group_members';

    protected $fillable = [
        'id',
        'group_id',
        'user_id',
        'role',
    ];

    public $timestamps = false; // table uses joined_at, not created_at/updated_at

    public function group()
    {
        return $this->belongsTo(StudyGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
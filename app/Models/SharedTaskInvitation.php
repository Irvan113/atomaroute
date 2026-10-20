<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedTaskInvitation extends Model
{
    protected $fillable = [
        'task_id',
        'inviter_id',
        'invitee_id',
        'status',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}

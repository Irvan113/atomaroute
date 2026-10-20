<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_template_id',
        'sender_id',
        'receiver_id',
        'note',
        'status',
    ];

    public function template()
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

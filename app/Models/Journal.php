<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'reference_url',
        'mood',
        'tags',
        'images',
    ];

    protected $casts = [
        'tags' => 'array',
        'images' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outgoingLinks()
    {
        return $this->belongsToMany(Journal::class, 'journal_links', 'source_journal_id', 'target_journal_id')
                    ->withPivot('link_text')
                    ->withTimestamps();
    }

    public function incomingLinks()
    {
        return $this->belongsToMany(Journal::class, 'journal_links', 'target_journal_id', 'source_journal_id')
                    ->withPivot('link_text')
                    ->withTimestamps();
    }

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class)->orderBy('created_at', 'desc');
    }
}


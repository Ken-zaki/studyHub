<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashcardDeck extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'description'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function flashcards()
    {
        return $this->hasMany(Flashcard::class, 'deck_id')->orderBy('created_at');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model
{
    /** @use HasFactory<\Database\Factories\FlashcardFactory> */
    use HasFactory;

    protected $fillable = [
        'deck_id',
        'question',
        'answer',
    ];

    protected $casts = [
        'deck_id' => 'integer',
        'question' => 'string',
        'answer' => 'string',
    ];

    public function deck()
    {
        return $this->belongsTo(Deck::class);
    }
}

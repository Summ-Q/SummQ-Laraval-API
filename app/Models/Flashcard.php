<?php

namespace App\Models;

use Database\Factories\FlashcardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Flashcard extends Model {
    /** @use HasFactory<FlashcardFactory> */
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

    public function deck() {
        return $this->belongsTo(Deck::class);
    }
}

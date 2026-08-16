<?php

namespace App\Models;

use Database\Factories\FlashcardFactory;
use Illuminate\Database\Eloquent\Builder;
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

    public function studyProgress() {
        return $this->hasOne(StudyProgress::class);
    }

    public function reviewLogs() {
        return $this->hasMany(ReviewLog::class);
    }

    public function scopeDueForUser(Builder $query, int $userId): Builder {
        return $query->select('flashcards.id', 'flashcards.deck_id', 'flashcards.question', 'flashcards.answer')
            ->leftJoin('study_progress', function ($join) use ($userId) {
                $join->on('flashcards.id', '=', 'study_progress.flashcard_id')
                    ->where('study_progress.user_id', '=', $userId);
            })
            ->where(function ($q) {
                $q->whereNull('study_progress.id')
                    ->orWhere('study_progress.next_review_due_at', '<=', now());
            });
    }
}

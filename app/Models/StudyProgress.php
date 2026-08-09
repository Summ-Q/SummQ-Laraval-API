<?php

namespace App\Models;

use Database\Factories\StudyProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyProgress extends Model {
    /** @use HasFactory<StudyProgressFactory> */
    use HasFactory;

    protected $table = "study_progress";

    protected $fillable = [
        'user_id',
        'flashcard_id',
        'review_count',
        'average_score',
        'last_reviewed_at',
        'next_review_due_at',
    ];

    protected $casts = [
        'last_reviewed_at' => 'datetime',
        'next_review_due_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function flashcard() {
        return $this->belongsTo(Flashcard::class);
    }
}

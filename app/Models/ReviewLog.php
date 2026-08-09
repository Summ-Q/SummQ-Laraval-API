<?php

namespace App\Models;

use Database\Factories\ReviewLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewLog extends Model {
    /** @use HasFactory<ReviewLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'flashcard_id',
        'score',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function flashcard() {
        return $this->belongsTo(Flashcard::class);
    }
}

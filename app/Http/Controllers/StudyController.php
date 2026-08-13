<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\Flashcard;
use App\Models\ReviewLog;
use App\Models\StudyProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class StudyController extends Controller {
    // GET /api/decks/{deck}/study
    public function index(Request $request, Deck $deck) {
        Gate::authorize('access-deck', $deck);

        $flashcards = $deck->flashcards()->select('id', 'deck_id', 'question', 'answer')->get();
        return response()->json([
            'message' => 'Flashcards retrieved successfully.',
            'data' => [
                'deck_id' => (int) $deck->id,
                'flashcards' => $flashcards,
            ],
        ], 200);
    }

    // POST /api/reviews/{flashcard}
    public function logReview(Request $request, Flashcard $flashcard) {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'in:0,1']
        ]);

        $flashcard->loadMissing('deck');
        Gate::authorize('access-deck', $flashcard->deck);

        $progress = DB::transaction(function () use ($request, $flashcard, $validated) {
            $now = now();

            ReviewLog::create([
                'user_id' => $request->user()->id,
                'flashcard_id' => $flashcard->id,
                'score' => $validated['score'],
            ]);

            // if the score is 1, then don't show it for ahhh 3 days, else if 0 then show it to them tomorrow
            $daysToAdd = $validated['score'] === 1 ? 3 : 1;
            $nextDue = $now->copy()->addDays($daysToAdd);

            $progress = StudyProgress::firstOrNew([
                'user_id' => $request->user()->id,
                'flashcard_id' => $flashcard->id,
            ]);

            $previousCount = (int) ($progress->review_count ?? 0);
            $previousAverage = (float) ($progress->average_score ?? 0);
            $newCount = $previousCount + 1;
            $newAverage = (($previousAverage * $previousCount) + $validated['score']) / $newCount;

            $progress->review_count = $newCount;
            $progress->average_score = round($newAverage, 2);
            $progress->last_reviewed_at = $now;
            $progress->next_review_due_at = $nextDue;
            $progress->save();

            return $progress;
        });

        return response()->json([
            'message' => 'Review logged and next due date calculated',
            'data' => [
                'id' => $progress->id,
                'flashcard_id' => $progress->flashcard_id,
                'average_score' => $progress->average_score,
                'next_review_due_at' => $progress->next_review_due_at,
            ]
        ], 200);
    }
}
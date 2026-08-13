<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\Flashcard;
use App\Models\ReviewLog;
use App\Models\StudyProgress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

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
            'score' => ['required', 'integer', 'in:0,1'],
            'days_to_add' => ['nullable', 'integer', 'min:0'],
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

            $daysToAdd = $request->input('days_to_add');

            if ($daysToAdd === null) {
                $pythonApiUrl = rtrim(config('services.python_api.url'), '/').'/ds/review-interval';
                $internalToken = config('services.python_api.internal_token');

                // FOR LOCAL TESTING
                // Http::fake([
                //     $pythonApiUrl => Http::response([
                //         'status' => 'success',
                //         'days_to_add' => 5,
                //     ], 200)
                // ]);
                // END LOCAL TESTING

                $pendingRequest = Http::withHeaders(['X-Internal-Token' => $internalToken])
                    ->acceptJson()
                    ->timeout(60);

                try {
                    $response = $pendingRequest->post($pythonApiUrl, [
                        'score' => $validated['score'],
                        'question' => $flashcard->question,
                        'answer' => $flashcard->answer,
                    ]);

                    if ($response->successful()) {
                        $apiDaysToAdd = $response->json('days_to_add');

                        if (is_numeric($apiDaysToAdd) && (int) $apiDaysToAdd >= 0) {
                            $daysToAdd = (int) $apiDaysToAdd;
                        }
                    }
                } catch (ConnectionException $e) {
                    return response()->json(['message' => 'Review scheduling service is unavailable.'], 503);
                }
            }

            if (! is_numeric($daysToAdd) || (int) $daysToAdd < 0) {
                return response()->json(['message' => 'days_to_add is required and it can\'t be a non-negative integer.'], 422);
            }

            $nextDue = $now->copy()->addDays((int) $daysToAdd);

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
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

        $flashcards = $deck->flashcards()->dueForUser($request->user()->id)->get();

        return response()->json([
            'message' => 'Study session loaded successfully.',
            'data' => [
                'deck_id' => (int) $deck->id,
                'due_count' => $flashcards->count(),
                'flashcards' => $flashcards,
            ],
        ], 200);
    }

    // POST /api/reviews/{flashcard}
    public function logReview(Request $request, Flashcard $flashcard) {
        $validated = $request->validate([
            'score' => ['required', 'integer', 'in:1,4'],
        ]);

        $flashcard->loadMissing('deck');
        Gate::authorize('access-deck', $flashcard->deck);

        $studyProgress = $flashcard->studyProgress()->where('user_id', $request->user()->id)->first();

        $daysToAdd = $this->fetchReviewIntervalFromDS($studyProgress);

        if ($daysToAdd === null) {
            return response()->json(['message' => 'Failed to calculate next review interval from DS service.'], 502);
        }

        $progress = DB::transaction(function () use ($request, $flashcard, $validated, $daysToAdd, $studyProgress) {
            $now = now();
            $userId = $request->user()->id;

            ReviewLog::create([
                'user_id' => $userId,
                'flashcard_id' => $flashcard->id,
                'score' => $validated['score'],
            ]);

            $previousCount = (int) ($studyProgress->review_count ?? 0);
            $previousAverage = (float) ($studyProgress->average_score ?? 0);

            $newCount = $previousCount + 1;
            $newAverage = (($previousAverage * $previousCount) + $validated['score']) / $newCount;

            $studyProgress->review_count = $newCount;
            $studyProgress->average_score = round($newAverage, 2);
            $studyProgress->last_reviewed_at = $now;
            $studyProgress->next_review_due_at = $now->copy()->addDays($daysToAdd);

            $studyProgress->save();

            return $studyProgress;
        });

        return response()->json([
            'message' => 'Review logged and next due date calculated',
            'data' => [
                'id' => $progress->id,
                'flashcard_id' => $progress->flashcard_id,
                'average_score' => $progress->average_score,
                'next_review_due_at' => $progress->next_review_due_at,
            ],
        ], 200);
    }

    private function fetchReviewIntervalFromDS(?StudyProgress $studyProgress = null): ?int {
        $apiUrlConfig = config('services.python_api.url');
        $internalToken = config('services.python_api.internal_token');

        if (! $apiUrlConfig || ! $internalToken) {
            return null;
        }

        $pythonApiUrl = rtrim($apiUrlConfig, '/').'/ds/review-interval';

        $payload = [];

        if ($studyProgress) {
            $payload['avg_score'] = (float) ($studyProgress->average_score ?? 0);
            $payload['past_reviews_count'] = (int) ($studyProgress->review_count ?? 0);
            $payload['days_since_last_review'] = $studyProgress->last_reviewed_at
                ? max(0, (int) now()->diffInDays($studyProgress->last_reviewed_at))
                : 0;
        }

        try {
            $response = Http::withHeaders(['X-Internal-Token' => $internalToken])
                ->acceptJson()
                ->timeout(20)
                ->post($pythonApiUrl, $payload);

            if ($response->successful()) {
                $apiDaysToAdd = $response->json('days_to_add');

                if (is_numeric($apiDaysToAdd) && (int) $apiDaysToAdd >= 0) {
                    return (int) $apiDaysToAdd;
                }
            }
        } catch (ConnectionException $e) {
            // Fails silently here and returns null to trigger the 502 in the main controller
        }

        return null;
    }
}

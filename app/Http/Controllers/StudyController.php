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
use Illuminate\Support\Facades\Log;

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
            'score' => ['required', 'numeric', 'between:1,4'],
        ]);

        $validated['score'] = (int) $validated['score'];

        $flashcard->loadMissing('deck');
        Gate::authorize('access-deck', $flashcard->deck);

        $studyProgress = $flashcard->studyProgress()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['review_count' => 0, 'average_score' => 0]
        );

        $daysToAdd = $this->fetchReviewIntervalFromDS($studyProgress);
        // $daysToAdd = $validated['score'] === 1 ? 1 : 3; // For now, just use a simple rule: 1 = 1 day, 4 = 3 days

        if ($daysToAdd === null) {
            return response()->json(['message' => 'Failed to calculate next review interval from DS service.', 'daysToAdd' => $daysToAdd], 502);
        }

        try {
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
        } catch (\Throwable $e) {
            Log::error('logReview failed', [
                'flashcard_id' => $flashcard->id,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'previous' => $e->getPrevious()?->getMessage(),
            ]);
            throw $e;
        }

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

        if (! $apiUrlConfig) {
            return null;
        }

        $pythonApiUrl = rtrim($apiUrlConfig, '/').'/review-interval';

        $payload = [];

        if ($studyProgress) {
            $payload['avg_score'] = (float) ($studyProgress->average_score ?? 0);
            $payload['past_reviews_count'] = (int) ($studyProgress->review_count ?? 0);
            $payload['days_since_last_review'] = $studyProgress->last_reviewed_at
                ? max(0, (int) now()->diffInDays($studyProgress->last_reviewed_at))
                : 0;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(20)
                ->post($pythonApiUrl, $payload);

            if ($response->successful()) {
                $apiDaysToAdd = $response->json('result')['suggested_interval_days'] ?? null;

                if (is_numeric($apiDaysToAdd) && (int) $apiDaysToAdd >= 0) {
                    return (int) $apiDaysToAdd;
                }
            }
        } catch (ConnectionException $e) {
            // Fails silently here and returns null to trigger the 502 in the main controller
        }

        return null;
    }

    public function getStudyPerformance(Request $request) {
        $data = Flashcard::CountReviewedLast7Days($request->user()->id)->get();

        $result = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $count = $data->firstWhere('day', $date)?->count ?? 0;
            $result[] = [
                'date' => $date,
                'count' => $count,
            ];
        }

        return response()->json([
            'message' => 'Study performance data retrieved successfully.',
            'data' => $result,
        ], 200);
    }
}

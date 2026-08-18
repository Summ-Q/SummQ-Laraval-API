<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Models\Flashcard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class FlashcardController extends Controller {
    public function index(Deck $deck) {
        Gate::authorize('access-deck', $deck);

        $cards = $deck->flashcards()->get();

        return response()->json([
            'message' => 'Cards retrieved successfully.',
            'data' => [
                'deck_id' => (int) $deck->id,
                'cards' => $cards,
            ],
        ]);
    }

    public function generate(Request $request, Deck $deck) {
        Gate::authorize('access-deck', $deck);

        $request->validate([
            'notes' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'], // 10MB
        ]);

        if (! $request->filled('notes') && ! $request->hasFile('file')) {
            return response()->json(['message' => 'Either notes or a PDF file must be provided.'], 400);
        }

        $pythonApiUrl = rtrim(config('services.python_api.url'), '/').'/ai/generate-cards';
        $internalToken = config('services.python_api.internal_token');

        // FOR LOCAL TESTING
        Http::fake([
            $pythonApiUrl => Http::response([
                'status' => 'success',
                'cards' => [
                    ['question' => 'Fake Question 1?', 'answer' => 'Fake Answer 1'],
                    ['question' => 'Fake Question 2?', 'answer' => 'Fake Answer 2'],
                ],
            ], 200),
        ]);
        // END LOCAL TESTING

        $pendingRequest = Http::withHeaders(['X-Internal-Token' => $internalToken])
            ->acceptJson()
            ->timeout(60); // AI generation might need up to 60 seconds

        try {
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $response = $pendingRequest->attach(
                    'file',
                    fopen($file->path(), 'r'),
                    $file->getClientOriginalName()
                )->post($pythonApiUrl);
            } else {
                $response = $pendingRequest->post($pythonApiUrl, [
                    'text' => $request->input('notes'),
                ]);
            }
        } catch (ConnectionException $e) {
            return response()->json(['message' => 'AI generation service is currently unavailable.'], 503);
        }

        if ($response->failed()) {
            return response()->json(['message' => 'Failed to generate flashcards.'], 500);
        }

        $generatedCards = $response->json('cards');

        if (! is_array($generatedCards)) {
            return response()->json(['message' => 'Invalid response from AI service.'], 502);
        }

        $createdCards = DB::transaction(function () use ($deck, $generatedCards) {
            $cards = [];
            foreach ($generatedCards as $cardData) {
                if (isset($cardData['question'], $cardData['answer'])) {
                    $flashcard = $deck->flashcards()->create([
                        'question' => $cardData['question'],
                        'answer' => $cardData['answer'],
                    ]);

                    $this->createStudyProgressForCard($flashcard, $deck->user_id);

                    $cards[] = $flashcard;
                }
            }

            return $cards;
        });

        return response()->json([
            'message' => 'Cards generated successfully',
            'data' => [
                'deck_id' => (int) $deck->id,
                'cards' => $createdCards,
            ],
        ]);
    }

    private function createStudyProgressForCard(Flashcard $flashcard, int $userId): void {
        $flashcard->studyProgress()->create([
            'user_id' => $userId,
            'review_count' => 0,
            'average_score' => 0.0,
            'last_reviewed_at' => null,
            'next_review_due_at' => now(),
        ]);
    }
}

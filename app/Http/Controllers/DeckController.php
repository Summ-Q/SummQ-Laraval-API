<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use Illuminate\Http\Request;

class DeckController extends Controller {
    // To Show all the Decks (GET /api/decks)
    public function index(Request $request) {
        // only fetch the decks that belongs to the user
        $decks = $request->user()->decks()->select('id', 'name', 'created_at')->get();
        
        return response()->json([
            'data' => $decks
            ], 200);
        }

    // To Create a new Deck (POST /api/decks)
    public function store(Request $request) {
        // Validate the incoming JSON
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Create the deck attached to the authenticated user
        $deck = $request->user()->decks()->create([
            'name' => $validated['name']
        ]);

        return response()->json([
            'data' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'created_at' => $deck->created_at,
            ]
        ], 201);
    }

    // To Delete a Deck (DELETE /api/decks/{deck_id})
    public function destroy(Request $request, Deck $deck) {
        $deck->delete();
        
        return response()->json([
            'message' => 'Deck deleted successfully'
        ], 200);
    }
}
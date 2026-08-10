<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller {
    public function register(Request $request) {
        if (Auth::guard('sanctum')->check()) {
            return response()->json(['message' => 'You are already logged in.'], 400);
        }

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($validatedData);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request) {
        if (Auth::guard('sanctum')->check()) {
            return response()->json(['message' => 'You are already logged in.'], 400);
        }

        $validatedData = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validatedData)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = Auth::user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'user' => Auth::user(),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }
}

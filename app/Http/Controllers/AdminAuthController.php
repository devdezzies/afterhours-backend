<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'admin',
        ])) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->issueTokenResponse($user, 'admin');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function issueTokenResponse(User $user, string $tokenName, int $status = 200): JsonResponse
    {
        $user->tokens()->where('name', $tokenName)->delete();

        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ], $status);
    }
}
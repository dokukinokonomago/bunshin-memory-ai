<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSecretUnlockRequest;
use App\Models\SecretUnlockToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecretUnlockController extends Controller
{
    public function store(StoreSecretUnlockRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['パスワードが正しくありません。'],
            ]);
        }

        $plainTextToken = Str::random(40);
        $expiresAt = now()->addMinutes(SecretUnlockToken::TTL_MINUTES);

        $unlockToken = $user->secretUnlockTokens()->create([
            'token' => hash('sha256', $plainTextToken),
            'expires_at' => $expiresAt,
        ]);

        return response()->json([
            'data' => [
                'unlock_token' => $unlockToken->getKey().'|'.$plainTextToken,
                'expires_at' => $unlockToken->expires_at?->toAtomString(),
            ],
        ], 201);
    }
}

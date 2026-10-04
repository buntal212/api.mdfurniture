<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Authenticate a user with their username and password.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['username', 'password']);
        $user = User::query()
            ->where('username', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau kata sandi tidak valid.'],
            ]);
        }

        $token = $user->createToken('quasar-web')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
            ],
            'token' => $token,
        ]);
    }
}

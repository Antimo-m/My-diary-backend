<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SecretDiaryPasswordResetMail;
use App\Support\SecretDiarySession;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class SecretDiaryAuthController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SecretDiarySession::status($request, $request->user()),
        ]);
    }

    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->secret_diary_password) {
            throw ValidationException::withMessages([
                'password' => __('secret_diary.password_already_exists'),
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill([
            'secret_diary_password' => Hash::make($validated['password']),
            'secret_diary_password_set_at' => now(),
        ])->save();

        SecretDiarySession::unlock($request);

        return response()->json([
            'message' => 'Password Diario Segreto creata.',
            'data' => SecretDiarySession::status($request, $user->fresh()),
        ], 201);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user->secret_diary_password || ! Hash::check($validated['password'], $user->secret_diary_password)) {
            throw ValidationException::withMessages([
                'password' => __('secret_diary.invalid_password'),
            ]);
        }

        SecretDiarySession::unlock($request);

        return response()->json([
            'message' => 'Diario Segreto sbloccato.',
            'data' => SecretDiarySession::status($request, $user),
        ]);
    }

    public function lock(Request $request): JsonResponse
    {
        SecretDiarySession::lock($request);

        return response()->json([
            'message' => 'Diario Segreto bloccato.',
            'data' => SecretDiarySession::status($request, $request->user()),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $rateLimitKey = $this->resetRateLimitKey($request, $validated['email']);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'message' => __('secret_diary.reset_throttled', [
                    'seconds' => RateLimiter::availableIn($rateLimitKey),
                ]),
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $user = $request->user();

        if (Str::lower($validated['email']) === Str::lower($user->email) && $user->secret_diary_password) {
            /** @var PasswordBroker $broker */
            $broker = Password::broker('secret_diary');
            $token = $broker->createToken($user);
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $email = urlencode($user->email);
            $url = "{$frontendUrl}/#secret_reset_token={$token}&email={$email}";

            Mail::to($user)->send(new SecretDiaryPasswordResetMail($user, $url));
        }

        return response()->json([
            'message' => __('secret_diary.reset_sent'),
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();
        $broker = Password::broker('secret_diary');

        if (
            Str::lower($validated['email']) !== Str::lower($user->email)
            || ! $broker->tokenExists($user, $validated['token'])
        ) {
            throw ValidationException::withMessages([
                'email' => __('passwords.token'),
            ]);
        }

        $user->forceFill([
            'secret_diary_password' => Hash::make($validated['password']),
            'secret_diary_password_set_at' => now(),
        ])->save();

        $broker->deleteToken($user);
        SecretDiarySession::lock($request);

        return response()->json([
            'message' => __('secret_diary.password_updated'),
        ]);
    }

    private function resetRateLimitKey(Request $request, string $email): string
    {
        return 'secret-diary-password-reset:'.$request->ip().':'.Str::lower($email);
    }
}

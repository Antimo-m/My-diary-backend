<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        event(new Registered($user));
        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Registrazione completata.',
            'user' => $this->serializeUser($request->user()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Login effettuato.',
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return response()->json([
            'message' => 'Se l email e registrata, riceverai un link per reimpostare la password.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->getKey())->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return response()->json([
            'message' => 'Password aggiornata.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function updateUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'show_welcome_modal' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'required', Rule::in(['it', 'en'])],
            'timezone' => ['sometimes', 'required', 'timezone'],
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Profilo aggiornato.',
            'user' => $this->serializeUser($user->refresh()),
        ]);
    }

    public function destroyAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        DB::transaction(function () use ($user): void {
            $user->diaryNotes()->pluck('cover_image')->filter()->each(
                fn (string $coverImage) => Storage::disk('local')->delete($coverImage)
            );
            $user->secretDiaryNotes()->pluck('cover_image')->filter()->each(
                fn (string $coverImage) => Storage::disk('local')->delete($coverImage)
            );

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();

            $user->delete();
        });

        return response()->json([
            'message' => 'Account eliminato.',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken && method_exists($accessToken, 'delete')) {
            $accessToken->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logout effettuato.',
        ]);
    }

    private function normalizeEmail(Request $request): void
    {
        if ($request->has('email')) {
            $request->merge([
                'email' => Str::lower(trim((string) $request->input('email'))),
            ]);
        }
    }

    private function serializeUser(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'show_welcome_modal',
            'email_notifications_enabled',
            'default_task_reminder',
            'locale',
            'timezone',
        ]);
    }
}

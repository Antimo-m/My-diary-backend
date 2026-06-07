<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecretDiarySession
{
    public const SESSION_KEY = 'secret_diary_unlocked_at';
    public const TIMEOUT_MINUTES = 5;

    public static function isUnlocked(Request $request): bool
    {
        if (! $request->user()?->secret_diary_password) {
            return false;
        }

        if (! $request->hasSession()) {
            $isUnlocked = Cache::has(self::cacheKey($request->user()));

            if ($isUnlocked) {
                self::unlock($request);
            }

            return $isUnlocked;
        }

        $unlockedAt = $request->session()->get(self::SESSION_KEY);

        if ($unlockedAt && now()->diffInMinutes($unlockedAt) < self::TIMEOUT_MINUTES) {
            self::unlock($request);

            return true;
        }

        self::lock($request);

        return false;
    }

    public static function unlock(Request $request): void
    {
        if (! $request->hasSession()) {
            Cache::put(self::cacheKey($request->user()), true, now()->addMinutes(self::TIMEOUT_MINUTES));

            return;
        }

        $request->session()->put(self::SESSION_KEY, now());
    }

    public static function lock(Request $request): void
    {
        if (! $request->hasSession()) {
            Cache::forget(self::cacheKey($request->user()));

            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    public static function status(Request $request, User $user): array
    {
        return [
            'has_password' => (bool) $user->secret_diary_password,
            'unlocked' => self::isUnlocked($request),
            'timeout_minutes' => self::TIMEOUT_MINUTES,
        ];
    }

    private static function cacheKey(?User $user): string
    {
        return 'secret_diary_unlocked_user_'.($user?->getKey() ?? 'guest');
    }
}

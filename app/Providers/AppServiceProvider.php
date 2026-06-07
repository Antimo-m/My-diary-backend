<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinutes(45, 2)
                ->by($request->ip().'|'.$email)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Troppi tentativi di reset password. Riprova piu tardi.',
                    ], 429, $headers);
                });
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}?reset_token={$token}&email={$email}";
        });
    }
}

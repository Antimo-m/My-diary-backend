<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        // Unica fonte di verita per la robustezza delle password account:
        // il frontend non replica queste regole, mostra solo gli errori restituiti.
        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers()->symbols());

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email', 'guest'));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));

        RateLimiter::for('api-read', function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('api-write', function (Request $request): Limit {
            return Limit::perMinute(45)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('stats', function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Raccolta crash frontend: endpoint pubblico, quindi tetto severo per
        // IP (config/monitoring.php). Un client sano non supera mai questa
        // soglia grazie al dedupe del modulo React.
        RateLimiter::for('frontend-errors', fn (Request $request): Limit => Limit::perMinute((int) config('monitoring.reports_per_minute'))->by($request->ip()));

        // Segnalazioni utenti: gia dietro autenticazione, il tetto orario per
        // utente chiude la porta a flood e spam.
        RateLimiter::for('user-reports', fn (Request $request): Limit => Limit::perHour(5)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

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
            $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            $email = urlencode($notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/#reset_token={$token}&email={$email}";
        });
    }
}

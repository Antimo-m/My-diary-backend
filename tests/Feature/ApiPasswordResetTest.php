<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_password_reset_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk()
            ->assertJsonPath('message', 'Se l email e registrata, riceverai un link per reimpostare la password.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_api_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->postJson('/api/reset-password', [
                'email' => $user->email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'token' => $notification->token,
            ])->assertOk()
                ->assertJsonPath('message', 'Password aggiornata.');

            return Hash::check('Password123!', $user->fresh()->password);
        });
    }

    public function test_authentication_normalizes_email_and_only_exposes_safe_user_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'secret_diary_password' => Hash::make('SecretPass123!'),
        ]);

        $this->withSession([])
            ->postJson('/api/login', [
                'email' => '  USER@EXAMPLE.COM ',
                'password' => 'password',
            ])->assertOk()
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.secret_diary_password')
            ->assertJsonMissingPath('user.secret_diary_password_set_at')
            ->assertJsonMissingPath('user.remember_token');
    }

    public function test_password_reset_revokes_existing_tokens_and_sessions(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->createToken('existing-session');
        DB::table('sessions')->insert([
            'id' => 'existing-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $this->postJson('/api/reset-password', [
                'email' => strtoupper($user->email),
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
                'token' => $notification->token,
            ])->assertOk();

            return true;
        });

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }
}

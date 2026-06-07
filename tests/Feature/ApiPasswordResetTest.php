<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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
}

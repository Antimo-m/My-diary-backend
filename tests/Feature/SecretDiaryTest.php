<?php

namespace Tests\Feature;

use App\Mail\SecretDiaryPasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SecretDiaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_unlock_lock_and_check_secret_diary_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/secret-diary/status')
            ->assertOk()
            ->assertJsonPath('data.has_password', false)
            ->assertJsonPath('data.unlocked', false);

        $this->actingAs($user)
            ->postJson('/api/secret-diary/setup', [
                'password' => 'SecretPass123!',
                'password_confirmation' => 'SecretPass123!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_password', true)
            ->assertJsonPath('data.unlocked', true);

        $this->assertTrue(Hash::check('SecretPass123!', $user->fresh()->secret_diary_password));

        $this->actingAs($user)
            ->postJson('/api/secret-diary/lock')
            ->assertOk()
            ->assertJsonPath('data.unlocked', false);

        $this->actingAs($user)
            ->postJson('/api/secret-diary/unlock', ['password' => 'wrong'])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->postJson('/api/secret-diary/unlock', ['password' => 'SecretPass123!'])
            ->assertOk()
            ->assertJsonPath('data.unlocked', true);
    }

    public function test_secret_notes_require_secret_unlock(): void
    {
        $user = User::factory()->create([
            'secret_diary_password' => Hash::make('SecretPass123!'),
            'secret_diary_password_set_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/secret-diary/notes')
            ->assertStatus(423);

        $this->actingAs($user)
            ->postJson('/api/secret-diary/unlock', ['password' => 'SecretPass123!'])
            ->assertOk();

        $createResponse = $this->actingAs($user)
            ->postJson('/api/secret-diary/notes', [
                'entry_date' => '2026-06-03',
                'title' => 'Pagina segreta',
                'body' => 'Contenuto privato.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Pagina segreta');

        $this->actingAs($user)
            ->getJson('/api/secret-diary/notes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $createResponse->json('data.id'));
    }

    public function test_secret_diary_password_can_be_reset_with_dedicated_token(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'secret_diary_password' => Hash::make('SecretPass123!'),
            'secret_diary_password_set_at' => now(),
        ]);

        $this->actingAs($user)
            ->withHeader('Accept-Language', 'it')
            ->postJson('/api/secret-diary/forgot-password', ['email' => $user->email])
            ->assertOk();

        Mail::assertSent(SecretDiaryPasswordResetMail::class, function (SecretDiaryPasswordResetMail $mail) use ($user): bool {
            $query = [];
            parse_str((string) parse_url($mail->url, PHP_URL_QUERY), $query);

            $this->actingAs($user)
                ->withHeader('Accept-Language', 'it')
                ->postJson('/api/secret-diary/reset-password', [
                    'email' => $user->email,
                    'password' => 'NewSecret123!',
                    'password_confirmation' => 'NewSecret123!',
                    'token' => $query['secret_reset_token'],
                ])
                ->assertOk()
                ->assertJsonPath('message', 'Password Diario Segreto aggiornata.');

            return Hash::check('NewSecret123!', $user->fresh()->secret_diary_password);
        });
    }
}

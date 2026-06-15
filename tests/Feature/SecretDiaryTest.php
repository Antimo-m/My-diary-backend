<?php

namespace Tests\Feature;

use App\Mail\SecretDiaryPasswordResetMail;
use App\Models\User;
use App\Support\SecretDiarySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecretDiaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

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
            $fragment = [];
            parse_str((string) parse_url($mail->url, PHP_URL_FRAGMENT), $fragment);

            $this->assertNull(parse_url($mail->url, PHP_URL_QUERY));
            $this->assertArrayHasKey('secret_reset_token', $fragment);

            $this->actingAs($user)
                ->withHeader('Accept-Language', 'it')
                ->postJson('/api/secret-diary/reset-password', [
                    'email' => $user->email,
                    'password' => 'NewSecret123!',
                    'password_confirmation' => 'NewSecret123!',
                    'token' => $fragment['secret_reset_token'],
                ])
                ->assertOk()
                ->assertJsonPath('message', 'Password Diario Segreto aggiornata.');

            return Hash::check('NewSecret123!', $user->fresh()->secret_diary_password);
        });
    }

    public function test_secret_diary_reset_cannot_target_another_authenticated_user(): void
    {
        Mail::fake();

        $attacker = User::factory()->create([
            'secret_diary_password' => Hash::make('AttackerSecret123!'),
        ]);
        $victim = User::factory()->create([
            'secret_diary_password' => Hash::make('VictimSecret123!'),
        ]);

        $this->actingAs($attacker)
            ->postJson('/api/secret-diary/forgot-password', ['email' => $victim->email])
            ->assertOk();

        Mail::assertNothingSent();

        $token = Password::broker('secret_diary')->createToken($victim);

        $this->actingAs($attacker)
            ->postJson('/api/secret-diary/reset-password', [
                'email' => $victim->email,
                'password' => 'ChangedSecret123!',
                'password_confirmation' => 'ChangedSecret123!',
                'token' => $token,
            ])
            ->assertUnprocessable();

        $this->assertTrue(Hash::check('VictimSecret123!', $victim->fresh()->secret_diary_password));
    }

    public function test_sessionless_secret_unlock_is_scoped_to_the_bearer_token(): void
    {
        $user = User::factory()->create([
            'secret_diary_password' => Hash::make('SecretPass123!'),
        ]);
        $firstRequest = Request::create('/api/secret-diary/unlock', 'POST', server: [
            'HTTP_AUTHORIZATION' => 'Bearer first-token',
        ]);
        $firstRequest->setUserResolver(fn () => $user);
        $secondRequest = Request::create('/api/secret-diary/status', 'GET', server: [
            'HTTP_AUTHORIZATION' => 'Bearer second-token',
        ]);
        $secondRequest->setUserResolver(fn () => $user);

        SecretDiarySession::unlock($firstRequest);

        $this->assertTrue(SecretDiarySession::isUnlocked($firstRequest));
        $this->assertFalse(SecretDiarySession::isUnlocked($secondRequest));
    }

    public function test_secret_diary_covers_are_private_and_require_the_owner_unlock(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $owner = User::factory()->create([
            'secret_diary_password' => Hash::make('SecretPass123!'),
        ]);
        $otherUser = User::factory()->create([
            'secret_diary_password' => Hash::make('OtherSecret123!'),
        ]);

        $this->actingAs($owner)
            ->postJson('/api/secret-diary/unlock', ['password' => 'SecretPass123!'])
            ->assertOk();

        $response = $this->actingAs($owner)->post('/api/secret-diary/notes', [
            'entry_date' => '2026-06-08',
            'title' => 'Pagina con cover privata',
            'body' => 'Contenuto protetto.',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ])->assertCreated();

        $note = $owner->secretDiaryNotes()->firstOrFail();
        Storage::disk('local')->assertExists($note->cover_image);
        Storage::disk('public')->assertMissing($note->cover_image);

        $coverUrl = parse_url($response->json('data.cover_image_url'), PHP_URL_PATH);

        $coverResponse = $this->actingAs($owner)
            ->get($coverUrl)
            ->assertOk();

        $this->assertStringContainsString('private', (string) $coverResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $coverResponse->headers->get('Cache-Control'));

        $this->actingAs($owner)
            ->postJson('/api/secret-diary/lock')
            ->assertOk();

        $this->actingAs($owner)
            ->get($coverUrl)
            ->assertStatus(423);

        $this->actingAs($otherUser)
            ->postJson('/api/secret-diary/unlock', ['password' => 'OtherSecret123!'])
            ->assertOk();

        $this->actingAs($otherUser)
            ->get($coverUrl)
            ->assertNotFound();
    }
}

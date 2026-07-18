<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationPasswordRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le regole di robustezza vivono solo nel backend (Password::defaults in
     * AppServiceProvider): questi test certificano il contratto su cui il
     * frontend si appoggia senza duplicare la validazione.
     */
    private function registrationPayload(string $password): array
    {
        return [
            'name' => 'Nuovo Utente',
            'email' => 'nuovo@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    public function test_registration_accepts_a_password_matching_the_default_rules(): void
    {
        $this->postJson('/api/register', $this->registrationPayload('Valida123!'))
            ->assertCreated()
            ->assertJsonPath('user.email', 'nuovo@example.com');
    }

    public function test_registration_rejects_passwords_below_the_default_rules(): void
    {
        $weakPasswords = [
            'Corta1!',        // meno di 8 caratteri
            'minuscola123!',  // nessuna maiuscola
            'MAIUSCOLA123!',  // nessuna minuscola
            'SenzaNumeri!',   // nessun numero
            'SenzaSimboli123', // nessun simbolo
        ];

        foreach ($weakPasswords as $password) {
            $this->postJson('/api/register', $this->registrationPayload($password))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }
    }

    public function test_password_reset_applies_the_same_default_rules(): void
    {
        $user = \App\Models\User::factory()->create();
        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'password' => 'debole',
            'password_confirmation' => 'debole',
            'token' => $token,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_defaults_are_seeded_in_english_for_english_users(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->getJson('/api/bacheca/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.0.title', 'To do')
            ->assertJsonPath('columns.1.title', 'In progress')
            ->assertJsonPath('columns.2.title', 'Done')
            ->assertJsonPath('labels.0.name', 'Personal');
    }

    public function test_board_defaults_are_seeded_in_italian_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/bacheca/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.0.title', 'Cose da fare')
            ->assertJsonPath('columns.1.title', 'In corso')
            ->assertJsonPath('columns.2.title', 'Completate')
            ->assertJsonPath('labels.0.name', 'Lavoro');
    }

    public function test_api_messages_follow_the_user_locale(): void
    {
        $italian = User::factory()->create(['locale' => 'it']);
        $english = User::factory()->create(['locale' => 'en']);

        $this->actingAs($italian)
            ->postJson('/api/bacheca/labels', ['name' => 'Palestra', 'color' => '#22c55e'])
            ->assertCreated()
            ->assertJsonPath('message', 'Etichetta creata.');

        $this->actingAs($english)
            ->postJson('/api/bacheca/labels', ['name' => 'Gym', 'color' => '#22c55e'])
            ->assertCreated()
            ->assertJsonPath('message', 'Label created.');
    }

    public function test_accept_language_header_drives_guest_responses(): void
    {
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If the email is registered, you will receive a link to reset your password.');
    }
}

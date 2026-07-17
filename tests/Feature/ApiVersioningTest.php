<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_prefix_serves_the_same_api(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/v1/home')->assertOk();

        $this->actingAs($user)
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->actingAs($user)
            ->getJson('/api/v1/bacheca/daily?date=2026-06-01')
            ->assertOk()
            ->assertJsonPath('columns.0.title', 'Cose da fare');
    }

    public function test_legacy_unversioned_paths_keep_working(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/home')->assertOk();
        $this->actingAs($user)->getJson('/api/user')->assertOk();
    }
}

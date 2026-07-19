<?php

namespace Tests\Feature;

use App\Models\FrontendError;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private function validReport(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'error',
            'message' => 'TypeError: x is not a function',
            'stack' => "TypeError: x is not a function\n  at App (App.jsx:10)",
            'component_stack' => "\n  at App",
            'source' => 'ErrorBoundary',
            'fingerprint' => 'a1b2c3d4e5f60718',
            'url' => 'https://mydiary.test/diary?page=2#reset_token=super-segreto',
            'route' => '/diary',
            'user_agent' => 'Mozilla/5.0 (Macintosh) TestBrowser/1.0',
            'browser' => 'Chrome',
            'os' => 'macOS',
            'viewport' => '1440x900',
            'language' => 'it-IT',
            'app_version' => '1.4.0',
            'commit_sha' => 'abc1234',
            'environment' => 'production',
            'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

    public function test_guests_can_report_errors_and_the_url_fragment_is_stripped(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport())->assertNoContent();

        $error = FrontendError::sole();
        $this->assertNull($error->user_id);
        $this->assertSame('https://mydiary.test/diary?page=2', $error->url);
        $this->assertSame('/diary', $error->page);
        $this->assertStringNotContainsString('reset_token', $error->url);
        $this->assertSame('a1b2c3d4e5f60718', $error->fingerprint);
        $this->assertSame('macOS', $error->os);
        $this->assertSame('production', $error->environment);
    }

    public function test_reports_without_client_fingerprint_get_a_server_side_one(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport(['fingerprint' => null]))->assertNoContent();

        $this->assertNotEmpty(FrontendError::sole()->fingerprint);
    }

    public function test_malformed_fingerprints_are_rejected(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport(['fingerprint' => 'DROP TABLE users']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('fingerprint');
    }

    public function test_health_events_are_accepted_with_their_payload(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport([
            'kind' => 'event',
            'message' => 'api.slow',
            'source' => 'health',
            'data' => ['endpoint' => '/diary-notes', 'duration_ms' => 4200],
        ]))->assertNoContent();

        $event = FrontendError::sole();
        $this->assertSame('event', $event->kind);
        $this->assertSame(4200, $event->data['duration_ms']);
    }

    public function test_authenticated_reports_are_linked_to_the_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/frontend-errors', $this->validReport())
            ->assertNoContent();

        $this->assertSame($user->id, FrontendError::sole()->user_id);
    }

    public function test_oversized_or_incomplete_reports_are_rejected(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport(['message' => str_repeat('a', 1001)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->postJson('/api/frontend-errors', ['message' => 'solo message'])
            ->assertUnprocessable();
    }

    public function test_collection_endpoint_disappears_when_the_feature_flag_is_off(): void
    {
        config(['features.monitoring' => false]);

        $this->postJson('/api/frontend-errors', $this->validReport())->assertNotFound();
    }

    public function test_monitoring_endpoints_require_an_admin_role(): void
    {
        $this->getJson('/api/monitoring/errors')->assertUnauthorized();

        foreach (['user', 'support', 'developer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/monitoring/errors')->assertForbidden();
        }

        foreach (['admin', 'super_admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->getJson('/api/monitoring/errors')->assertOk();
        }
    }

    public function test_admins_can_list_search_stats_and_detail(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->postJson('/api/frontend-errors', $this->validReport())->assertNoContent();
        $this->postJson('/api/frontend-errors', $this->validReport([
            'message' => 'ReferenceError: y is not defined',
            'source' => 'unhandledrejection',
            'fingerprint' => 'ffffffff00000000',
            'browser' => 'Firefox',
        ]))->assertNoContent();

        $this->actingAs($admin)
            ->getJson('/api/monitoring/errors?q=ReferenceError')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.source', 'unhandledrejection');

        $this->actingAs($admin)
            ->getJson('/api/monitoring/errors/stats?days=7')
            ->assertOk()
            ->assertJsonPath('data.totals.errors', 2)
            ->assertJsonPath('data.totals.today', 2)
            ->assertJsonPath('data.totals.groups', 2)
            ->assertJsonStructure(['data' => ['period_days', 'totals' => ['errors', 'today', 'week', 'groups', 'affected_users'], 'trend', 'top_groups', 'by_browser', 'by_page', 'by_version']]);

        $first = FrontendError::first();
        $this->actingAs($admin)
            ->getJson("/api/monitoring/errors/{$first->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.stack', $first->stack);
    }

    public function test_role_is_exposed_but_never_mass_assignable(): void
    {
        // refresh(): actingAs usa l'istanza in memoria della factory, che non
        // contiene le colonne con default DB come role.
        $user = User::factory()->create()->refresh();

        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.is_admin', false);

        $this->actingAs($user)
            ->putJson('/api/user', ['name' => 'Nuovo Nome', 'role' => 'super_admin', 'is_admin' => true])
            ->assertOk();

        $this->assertSame('user', $user->fresh()->role);
    }
}

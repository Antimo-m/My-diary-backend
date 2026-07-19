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
            'message' => 'TypeError: x is not a function',
            'stack' => "TypeError: x is not a function\n  at App (App.jsx:10)",
            'component_stack' => "\n  at App",
            'source' => 'ErrorBoundary',
            'url' => 'https://mydiary.test/diary?page=2#reset_token=super-segreto',
            'user_agent' => 'Mozilla/5.0 (Macintosh) TestBrowser/1.0',
            'browser' => 'Chrome',
            'app_version' => '1.4.0',
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
        $this->assertNotEmpty($error->fingerprint);
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

    public function test_same_crash_shares_the_same_fingerprint(): void
    {
        $this->postJson('/api/frontend-errors', $this->validReport())->assertNoContent();
        $this->postJson('/api/frontend-errors', $this->validReport(['url' => 'https://mydiary.test/bacheca']))->assertNoContent();

        $this->assertSame(1, FrontendError::distinct('fingerprint')->count('fingerprint'));
    }

    public function test_monitoring_endpoints_require_an_admin(): void
    {
        $this->getJson('/api/monitoring/errors')->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/monitoring/errors')->assertForbidden();
        $this->actingAs($user)->getJson('/api/monitoring/errors/stats')->assertForbidden();
    }

    public function test_admins_can_list_search_and_read_stats(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->postJson('/api/frontend-errors', $this->validReport())->assertNoContent();
        $this->postJson('/api/frontend-errors', $this->validReport([
            'message' => 'ReferenceError: y is not defined',
            'source' => 'unhandledrejection',
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
            ->assertJsonPath('data.totals.groups', 2)
            ->assertJsonStructure(['data' => ['period_days', 'totals', 'trend', 'top_groups', 'by_browser', 'by_page', 'by_version']]);
    }

    public function test_is_admin_flag_is_exposed_but_not_mass_assignable(): void
    {
        // refresh(): actingAs usa l'istanza in memoria della factory, che non
        // contiene le colonne con default DB come is_admin.
        $user = User::factory()->create()->refresh();

        $response = $this->actingAs($user)->getJson('/api/user')->assertOk();
        $response->assertJsonPath('user.is_admin', false);

        $this->actingAs($user)
            ->putJson('/api/user', ['name' => 'Nuovo Nome', 'is_admin' => true])
            ->assertOk();

        $this->assertFalse($user->fresh()->is_admin);
    }
}

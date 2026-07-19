<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserReportsTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'bug',
            'subject' => 'Il calendario non si apre',
            'message' => 'Su Safari il calendario della Bacheca non compare.',
            'fingerprint' => 'a1b2c3d4e5f60718',
            'context' => [
                'url' => 'https://mydiary.test/bacheca#reset_token=segreto',
                'route' => '/bacheca',
                'browser' => 'Safari',
                'os' => 'macOS',
                'app_version' => '1.4.0',
                'chiave_non_prevista' => 'da scartare',
            ],
        ], $overrides);
    }

    public function test_guests_cannot_submit_reports(): void
    {
        $this->postJson('/api/reports', $this->validPayload())->assertUnauthorized();
    }

    public function test_users_can_submit_a_report_with_whitelisted_context(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/reports', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.type', 'bug');

        $report = UserReport::sole();
        $this->assertSame($user->id, $report->user_id);
        $this->assertSame('https://mydiary.test/bacheca', $report->context['url']);
        $this->assertArrayNotHasKey('chiave_non_prevista', $report->context);
        $this->assertSame('a1b2c3d4e5f60718', $report->fingerprint);
    }

    public function test_invalid_type_or_oversized_fields_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/reports', $this->validPayload(['type' => 'insulto']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');

        $this->actingAs($user)
            ->postJson('/api/reports', $this->validPayload(['message' => str_repeat('a', 5001)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    }

    public function test_users_only_see_their_own_reports(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($author)->postJson('/api/reports', $this->validPayload())->assertCreated();

        $this->actingAs($other)->getJson('/api/reports/mine')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($author)->getJson('/api/reports/mine')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Il calendario non si apre');
    }

    public function test_admin_endpoints_require_the_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/admin/reports')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/reports/stats')->assertForbidden();
    }

    public function test_admins_can_filter_update_status_and_leave_an_audit_trail(): void
    {
        $author = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($author)->postJson('/api/reports', $this->validPayload())->assertCreated();
        $this->actingAs($author)->postJson('/api/reports', $this->validPayload([
            'type' => 'suggestion',
            'subject' => 'Tema ad alto contrasto',
        ]))->assertCreated();

        $this->actingAs($admin)->getJson('/api/admin/reports?type=bug')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'bug');

        $report = UserReport::where('type', 'bug')->sole();

        $this->actingAs($admin)
            ->patchJson("/api/admin/reports/{$report->id}", [
                'status' => 'in_progress',
                'assigned_to' => $admin->id,
                'admin_note' => 'Riprodotto su Safari 19.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.assignee.id', $admin->id);

        $entry = AuditLog::where('action', 'report.updated')->sole();
        $this->assertSame($admin->id, $entry->user_id);
        $this->assertSame('open', $entry->meta['from']['status']);
        $this->assertSame('in_progress', $entry->meta['to']['status']);

        $this->actingAs($admin)->getJson('/api/admin/reports/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.by_status.in_progress', 1);
    }

    public function test_reports_endpoints_disappear_when_the_feature_flag_is_off(): void
    {
        config(['features.reports' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/reports', $this->validPayload())->assertNotFound();
        $this->actingAs($user)->getJson('/api/reports/mine')->assertNotFound();
    }
}

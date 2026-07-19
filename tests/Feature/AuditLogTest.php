<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_deletion_leaves_an_audit_trail_that_survives_the_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/user', ['password' => 'password'])
            ->assertOk();

        $entry = AuditLog::sole();
        $this->assertSame('account.deleted', $entry->action);
        $this->assertSame($user->email, $entry->meta['email']);
        // L'utente non esiste piu: la riga resta, senza attore.
        $this->assertNull($entry->fresh()->user_id);
        $this->assertNotNull($entry->created_at);
    }
}

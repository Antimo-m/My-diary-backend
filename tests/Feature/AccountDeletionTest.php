<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_delete_an_account(): void
    {
        $this->deleteJson('/api/user', ['password' => 'password'])->assertUnauthorized();
    }

    public function test_deleting_the_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->deleteJson('/api/user', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_can_delete_their_own_account_and_all_owned_data_is_removed(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $user->createToken('device');
        DB::table('sessions')->insert([
            'id' => 'session-to-purge',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)->postJson('/api/diary-notes', [
            'entry_date' => '2026-06-01',
            'title' => 'Nota da rimuovere',
            'body' => 'Contenuto.',
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ])->assertCreated();

        $note = $user->diaryNotes()->firstOrFail();
        Storage::disk('local')->assertExists($note->cover_image);

        $this->actingAs($user)->postJson('/api/kanban/tasks', [
            'task_date' => '2026-06-01',
            'title' => 'Task da rimuovere',
        ])->assertCreated();

        $this->actingAs($user)
            ->deleteJson('/api/user', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('message', 'Account eliminato.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('diary_notes', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('kanban_tasks', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('kanban_columns', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        Storage::disk('local')->assertMissing($note->cover_image);
    }
}

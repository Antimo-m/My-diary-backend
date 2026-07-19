<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'show_welcome_modal', 'email_notifications_enabled', 'default_task_reminder', 'locale', 'timezone'])]
#[Hidden(['password', 'secret_diary_password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Ruoli disponibili, dal meno al piu privilegiato. Il ruolo non e MAI
     * assegnabile via API (fuori dal fillable): solo console o seed.
     */
    public const ROLES = ['user', 'support', 'developer', 'admin', 'super_admin'];

    public function hasRole(string ...$roles): bool
    {
        return $this->role === 'super_admin' || in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'secret_diary_password_set_at' => 'datetime',
            'show_welcome_modal' => 'boolean',
            'email_notifications_enabled' => 'boolean',
        ];
    }

    public function diaryNotes(): HasMany
    {
        return $this->hasMany(DiaryNote::class);
    }

    public function secretDiaryNotes(): HasMany
    {
        return $this->hasMany(SecretDiaryNote::class);
    }

    public function kanbanTasks(): HasMany
    {
        return $this->hasMany(KanbanTask::class);
    }

    public function kanbanColumns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(UserReport::class);
    }

    public function kanbanLabels(): HasMany
    {
        return $this->hasMany(KanbanLabel::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}

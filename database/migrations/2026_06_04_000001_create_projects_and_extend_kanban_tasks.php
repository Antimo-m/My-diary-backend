<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('kanban_column_id')
                ->constrained()
                ->nullOnDelete();
            $table->boolean('is_completed')->default(false)->after('status');
            $table->timestamp('completed_at')->nullable()->after('is_completed');

            $table->index(['user_id', 'project_id'], 'kanban_tasks_user_project_index');
            $table->index(['user_id', 'is_completed'], 'kanban_tasks_user_completion_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 8)->default('it')->after('default_task_reminder');
            $table->string('timezone', 64)->default('Europe/Rome')->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['locale', 'timezone']);
        });

        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->dropIndex('kanban_tasks_user_completion_index');
            $table->dropIndex('kanban_tasks_user_project_index');
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['is_completed', 'completed_at']);
        });

        Schema::dropIfExists('projects');
    }
};

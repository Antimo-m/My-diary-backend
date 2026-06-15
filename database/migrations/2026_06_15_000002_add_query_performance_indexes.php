<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_notes', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'diary_notes_user_created_index');
        });

        Schema::table('secret_diary_notes', function (Blueprint $table): void {
            $table->index(['user_id', 'created_at'], 'secret_diary_notes_user_created_index');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->index(['user_id', 'updated_at'], 'projects_user_updated_index');
        });

        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->index(
                ['user_id', 'project_id', 'task_date', 'position', 'id'],
                'kanban_tasks_daily_order_index',
            );
            $table->index(
                ['user_id', 'project_id', 'position', 'id'],
                'kanban_tasks_project_order_index',
            );
            $table->index(
                ['user_id', 'kanban_column_id', 'project_id', 'task_date', 'position'],
                'kanban_tasks_column_position_index',
            );
            $table->index(
                ['user_id', 'created_at'],
                'kanban_tasks_user_created_index',
            );
            $table->index(
                ['user_id', 'project_id', 'created_at'],
                'kanban_tasks_stats_created_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->dropIndex('kanban_tasks_daily_order_index');
            $table->dropIndex('kanban_tasks_project_order_index');
            $table->dropIndex('kanban_tasks_column_position_index');
            $table->dropIndex('kanban_tasks_user_created_index');
            $table->dropIndex('kanban_tasks_stats_created_index');
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex('projects_user_updated_index');
        });

        Schema::table('secret_diary_notes', function (Blueprint $table): void {
            $table->dropIndex('secret_diary_notes_user_created_index');
        });

        Schema::table('diary_notes', function (Blueprint $table): void {
            $table->dropIndex('diary_notes_user_created_index');
        });
    }
};

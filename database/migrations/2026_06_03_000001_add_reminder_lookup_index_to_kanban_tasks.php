<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->index(['reminder_sent_at', 'reminder_at'], 'kanban_tasks_reminder_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->dropIndex('kanban_tasks_reminder_lookup_index');
        });
    }
};

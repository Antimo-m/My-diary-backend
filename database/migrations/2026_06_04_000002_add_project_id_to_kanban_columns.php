<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_columns', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['user_id', 'project_id', 'position'], 'kanban_columns_user_project_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_columns', function (Blueprint $table) {
            $table->dropIndex('kanban_columns_user_project_position_index');
            $table->dropConstrainedForeignId('project_id');
        });
    }
};

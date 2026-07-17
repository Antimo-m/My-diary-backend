<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kanban_columns', function (Blueprint $table) {
            $table->date('date')->nullable()->after('project_id');
            $table->index(['user_id', 'date']);
        });

        DB::table('kanban_columns')
            ->whereNull('project_id')
            ->whereNull('date')
            ->update(['date' => now()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('kanban_columns', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropColumn('date');
        });
    }
};

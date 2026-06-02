<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('color', 32)->default('#22c55e');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'position']);
        });

        Schema::create('kanban_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 32)->default('#4f46e5');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('kanban_label_kanban_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kanban_label_id')->constrained()->cascadeOnDelete();

            $table->unique(['kanban_task_id', 'kanban_label_id'], 'kanban_task_label_unique');
        });

        Schema::table('kanban_tasks', function (Blueprint $table) {
            $table->foreignId('kanban_column_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->date('due_date')->nullable()->after('description');
            $table->string('color', 32)->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kanban_column_id');
            $table->dropColumn(['due_date', 'color']);
        });

        Schema::dropIfExists('kanban_label_kanban_task');
        Schema::dropIfExists('kanban_labels');
        Schema::dropIfExists('kanban_columns');
    }
};

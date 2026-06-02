<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('show_welcome_modal')->default(true)->after('remember_token');
            $table->boolean('email_notifications_enabled')->default(true)->after('show_welcome_modal');
            $table->string('default_task_reminder', 32)->default('none')->after('email_notifications_enabled');
        });

        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->time('due_time')->nullable()->after('due_date');
            $table->string('reminder_option', 32)->nullable()->after('due_time');
            $table->dateTime('custom_reminder_at')->nullable()->after('reminder_option');
            $table->dateTime('reminder_at')->nullable()->after('custom_reminder_at');
            $table->dateTime('reminder_sent_at')->nullable()->after('reminder_at');
        });
    }

    public function down(): void
    {
        Schema::table('kanban_tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'due_time',
                'reminder_option',
                'custom_reminder_at',
                'reminder_at',
                'reminder_sent_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'show_welcome_modal',
                'email_notifications_enabled',
                'default_task_reminder',
            ]);
        });
    }
};

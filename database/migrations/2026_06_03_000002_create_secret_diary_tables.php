<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('secret_diary_password')->nullable()->after('password');
            $table->timestamp('secret_diary_password_set_at')->nullable()->after('secret_diary_password');
        });

        Schema::create('secret_diary_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('photo_dedication', 180)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'entry_date']);
        });

        Schema::create('secret_diary_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_diary_password_reset_tokens');
        Schema::dropIfExists('secret_diary_notes');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['secret_diary_password', 'secret_diary_password_set_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fulltext indexes are a MySQL feature; the sqlite test database keeps
     * using the LIKE fallback implemented in the search scope.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('diary_notes', function (Blueprint $table): void {
            $table->fullText(['title', 'body'], 'diary_notes_title_body_fulltext');
        });

        Schema::table('secret_diary_notes', function (Blueprint $table): void {
            $table->fullText(['title', 'body'], 'secret_diary_notes_title_body_fulltext');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('diary_notes', function (Blueprint $table): void {
            $table->dropFullText('diary_notes_title_body_fulltext');
        });

        Schema::table('secret_diary_notes', function (Blueprint $table): void {
            $table->dropFullText('secret_diary_notes_title_body_fulltext');
        });
    }
};

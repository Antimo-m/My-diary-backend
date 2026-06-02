<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_notes', function (Blueprint $table) {
            $table->string('photo_dedication', 180)->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('diary_notes', function (Blueprint $table) {
            $table->dropColumn('photo_dedication');
        });
    }
};

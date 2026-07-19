<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('frontend_errors', function (Blueprint $table): void {
            // 'error' = crash; 'event' = segnale di salute applicativa
            // (timeout, API lente, upload falliti...): stessa pipeline.
            $table->string('kind', 20)->default('error')->index()->after('fingerprint');
            $table->string('route', 255)->nullable()->after('page');
            $table->string('os', 40)->nullable()->after('browser');
            $table->string('viewport', 20)->nullable()->after('os');
            $table->string('language', 10)->nullable()->after('viewport');
            $table->string('environment', 40)->nullable()->index()->after('app_version');
            $table->string('commit_sha', 64)->nullable()->after('environment');
            $table->json('data')->nullable()->after('commit_sha');
        });
    }

    public function down(): void
    {
        Schema::table('frontend_errors', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'route', 'os', 'viewport', 'language', 'environment', 'commit_sha', 'data']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Raggruppa occorrenze dello stesso crash (sha1 di source+message):
            // la dashboard ragiona per gruppi, non per righe.
            $table->char('fingerprint', 40)->index();
            $table->string('message', 1000);
            $table->mediumText('stack')->nullable();
            $table->mediumText('component_stack')->nullable();
            $table->string('source', 50)->index();
            $table->string('url', 2048);
            // Path normalizzato dell'URL, per le statistiche per pagina.
            $table->string('page', 255)->index();
            $table->string('user_agent', 500);
            $table->string('browser', 40)->nullable()->index();
            $table->string('app_version', 64)->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_errors');
    }
};

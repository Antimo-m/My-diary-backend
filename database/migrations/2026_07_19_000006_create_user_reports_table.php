<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reports', function (Blueprint $table): void {
            $table->id();
            // La segnalazione appartiene all'utente: cade con l'account.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index();
            $table->string('status', 20)->default('open')->index();
            $table->string('subject', 150);
            $table->text('message');
            // Correlazione (futura e presente) con i gruppi di frontend_errors.
            $table->string('fingerprint', 64)->nullable()->index();
            // Contesto tecnico raccolto automaticamente dal client, whitelistato
            // dal service: url, route, browser, os, viewport, lingua, versione.
            $table->json('context')->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('ip', 45)->nullable();
            // Workflow di lavorazione: assegnazione e nota interna.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};

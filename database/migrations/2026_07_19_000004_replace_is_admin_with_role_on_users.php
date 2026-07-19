<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un ruolo singolo per utente copre i bisogni attuali (gate dashboard,
     * futuro gestionale OTP) restando estendibile; un RBAC a tabelle pivot
     * arrivera solo se serviranno permessi granulari per risorsa.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('user')->index()->after('email_notifications_enabled');
        });

        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('email_notifications_enabled');
        });

        DB::table('users')->whereIn('role', ['admin', 'super_admin'])->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};

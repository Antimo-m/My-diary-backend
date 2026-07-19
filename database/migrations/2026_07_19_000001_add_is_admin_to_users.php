<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Flag minimo per il gate amministrativo (dashboard monitoraggio).
            // Volutamente fuori dal fillable del Model: si assegna solo da
            // console/seed, mai via API.
            $table->boolean('is_admin')->default(false)->after('email_notifications_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};

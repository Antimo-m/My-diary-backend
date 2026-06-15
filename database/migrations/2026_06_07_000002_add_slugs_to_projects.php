<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->unique(['user_id', 'slug']);
        });

        DB::table('projects')
            ->select(['id', 'user_id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    $base = Str::slug($project->name) ?: 'progetto';
                    $slug = $base;
                    $suffix = 2;

                    while (DB::table('projects')
                        ->where('user_id', $project->user_id)
                        ->where('slug', $slug)
                        ->exists()) {
                        $slug = "{$base}-{$suffix}";
                        $suffix++;
                    }

                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropUnique('projects_user_id_slug_unique');
            $table->dropColumn('slug');
        });
    }
};

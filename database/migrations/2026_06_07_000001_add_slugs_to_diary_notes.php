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
        $this->addSlugColumn('diary_notes');
        $this->addSlugColumn('secret_diary_notes');

        $this->backfillSlugs('diary_notes');
        $this->backfillSlugs('secret_diary_notes');
    }

    public function down(): void
    {
        $this->dropSlugColumn('secret_diary_notes');
        $this->dropSlugColumn('diary_notes');
    }

    private function addSlugColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'slug')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('title');
            $table->unique(['user_id', 'slug']);
        });
    }

    private function backfillSlugs(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'slug')) {
            return;
        }

        DB::table($tableName)
            ->select(['id', 'user_id', 'title'])
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(100, function ($notes) use ($tableName): void {
                foreach ($notes as $note) {
                    $base = Str::slug($note->title) ?: 'pagina-diario';
                    $slug = $base;
                    $suffix = 2;

                    while (DB::table($tableName)
                        ->where('user_id', $note->user_id)
                        ->where('slug', $slug)
                        ->exists()) {
                        $slug = "{$base}-{$suffix}";
                        $suffix++;
                    }

                    DB::table($tableName)
                        ->where('id', $note->id)
                        ->update(['slug' => $slug]);
                }
            });
    }

    private function dropSlugColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'slug')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            $table->dropUnique($tableName.'_user_id_slug_unique');
            $table->dropColumn('slug');
        });
    }
};

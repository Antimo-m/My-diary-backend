<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $this->recoverTableCovers('diary_notes');
        $this->recoverTableCovers('secret_diary_notes');
    }

    public function down(): void
    {
        // Historical files are intentionally retained in private storage.
    }

    private function recoverTableCovers(string $table): void
    {
        $private = Storage::disk('local');
        $public = Storage::disk('public');

        DB::table($table)
            ->whereNotNull('cover_image')
            ->select(['id', 'cover_image'])
            ->orderBy('id')
            ->chunkById(100, function ($notes) use ($private, $public): void {
                foreach ($notes as $note) {
                    $path = trim((string) $note->cover_image);

                    if ($path === '' || $private->exists($path) || ! $public->exists($path)) {
                        continue;
                    }

                    $private->put($path, $public->get($path));
                }
            });
    }
};

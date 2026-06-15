<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('secret_diary_notes')
            ->whereNotNull('cover_image')
            ->pluck('cover_image')
            ->filter()
            ->each(function (string $path): void {
                $public = Storage::disk('public');
                $private = Storage::disk('local');

                if (! $private->exists($path) && $public->exists($path)) {
                    $private->put($path, $public->get($path));
                }

                if ($private->exists($path)) {
                    $public->delete($path);
                }
            });
    }

    public function down(): void
    {
        DB::table('secret_diary_notes')
            ->whereNotNull('cover_image')
            ->pluck('cover_image')
            ->filter()
            ->each(function (string $path): void {
                $public = Storage::disk('public');
                $private = Storage::disk('local');

                if (! $public->exists($path) && $private->exists($path)) {
                    $public->put($path, $private->get($path));
                }
            });
    }
};

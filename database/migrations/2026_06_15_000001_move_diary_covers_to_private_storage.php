<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $this->moveCovers('public', 'local', true);
    }

    public function down(): void
    {
        $this->moveCovers('local', 'public', false);
    }

    private function moveCovers(string $sourceDisk, string $destinationDisk, bool $deleteSource): void
    {
        DB::table('diary_notes')
            ->whereNotNull('cover_image')
            ->select(['id', 'cover_image'])
            ->orderBy('id')
            ->chunkById(100, function ($notes) use ($sourceDisk, $destinationDisk, $deleteSource): void {
                $source = Storage::disk($sourceDisk);
                $destination = Storage::disk($destinationDisk);

                foreach ($notes as $note) {
                    $path = (string) $note->cover_image;

                    if ($path === '') {
                        continue;
                    }

                    if (! $destination->exists($path) && $source->exists($path)) {
                        $destination->put($path, $source->get($path));
                    }

                    if ($deleteSource && $destination->exists($path)) {
                        $source->delete($path);
                    }
                }
            });
    }
};

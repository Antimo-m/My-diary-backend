<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiaryNote;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiaryNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:24'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 8);

        $notes = $request->user()
            ->diaryNotes()
            ->when($validated['date'] ?? null, function ($query, $date): void {
                $query
                    ->where('entry_date', '>=', $date)
                    ->where('entry_date', '<', CarbonImmutable::parse($date)->addDay()->toDateString());
            })
            ->when($search !== '', fn ($query) => $query->search($search))
            ->latest('entry_date')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $notes->getCollection()
                ->map(fn (DiaryNote $note): array => $this->serializeNote($note))
                ->values(),
            'meta' => [
                'current_page' => $notes->currentPage(),
                'from' => $notes->firstItem(),
                'last_page' => $notes->lastPage(),
                'per_page' => $notes->perPage(),
                'to' => $notes->lastItem(),
                'total' => $notes->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateNote($request, true);
        $validated['slug'] = $this->uniqueSlug($request, $validated['title']);
        $validated['cover_image'] = $this->storeCoverImage($request);

        $note = $request->user()->diaryNotes()->create($validated);

        return response()->json([
            'message' => __('diary.note_created'),
            'data' => $this->serializeNote($note),
        ], 201);
    }

    public function show(Request $request, string $note): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeNote($this->findOwnedNote($request, $note)),
        ]);
    }

    public function cover(Request $request, string $note)
    {
        $diaryNote = $this->findOwnedNote($request, $note);

        abort_unless($diaryNote->cover_image, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($diaryNote->cover_image), 404);

        $response = response()->file($disk->path($diaryNote->cover_image), [
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }

    public function update(Request $request, string $note): JsonResponse
    {
        $diaryNote = $this->findOwnedNote($request, $note);
        $validated = $this->validateNote($request, false);

        if ($request->hasFile('cover_image')) {
            $this->deleteCoverImage($diaryNote);
            $validated['cover_image'] = $this->storeCoverImage($request);
        }

        $diaryNote->update($validated);

        return response()->json([
            'message' => __('diary.note_updated'),
            'data' => $this->serializeNote($diaryNote->fresh()),
        ]);
    }

    public function destroy(Request $request, string $note): JsonResponse
    {
        $diaryNote = $this->findOwnedNote($request, $note);
        $this->deleteCoverImage($diaryNote);
        $diaryNote->delete();

        return response()->json([
            'message' => __('diary.note_deleted'),
        ]);
    }

    private function validateNote(Request $request, bool $creating): array
    {
        return $request->validate([
            'entry_date' => [$creating ? 'required' : 'sometimes', 'date'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'body' => [$creating ? 'required' : 'sometimes', 'string', 'min:3'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'photo_dedication' => ['nullable', 'string', 'max:180'],
        ]);
    }

    private function findOwnedNote(Request $request, string $identifier): DiaryNote
    {
        return $request->user()
            ->diaryNotes()
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }

    private function uniqueSlug(Request $request, string $title): string
    {
        $base = Str::slug($title) ?: 'pagina-diario';
        $slug = $base;
        $suffix = 2;

        while ($request->user()->diaryNotes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeCoverImage(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('diary-covers', 'local')
            : null;
    }

    private function deleteCoverImage(DiaryNote $note): void
    {
        if ($note->cover_image) {
            Storage::disk('local')->delete($note->cover_image);
        }
    }

    private function serializeNote(DiaryNote $note): array
    {
        return [
            'id' => $note->id,
            'slug' => $note->slug,
            'route_identifier' => $note->slug ?: (string) $note->id,
            'entry_date' => $note->entry_date?->toDateString(),
            'formatted_date' => $note->entry_date?->translatedFormat('d F Y'),
            'title' => $note->title,
            'body' => $note->body,
            'excerpt' => Str::limit($note->body ?: __('diary.empty_excerpt'), 145),
            'photo_dedication' => $note->photo_dedication,
            'cover_image_url' => $note->coverImageUrl() ? url($note->coverImageUrl()) : null,
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }
}

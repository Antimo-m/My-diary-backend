<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecretDiaryNote;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SecretDiaryNoteController extends Controller
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
            ->secretDiaryNotes()
            ->when($validated['date'] ?? null, function ($query, $date): void {
                $query
                    ->where('entry_date', '>=', $date)
                    ->where('entry_date', '<', CarbonImmutable::parse($date)->addDay()->toDateString());
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('body', 'like', "%{$search}%");
                });
            })
            ->latest('entry_date')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $notes->getCollection()
                ->map(fn (SecretDiaryNote $note): array => $this->serializeNote($note))
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

        $note = $request->user()->secretDiaryNotes()->create($validated);

        return response()->json([
            'message' => __('secret_diary.note_created'),
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
        $secretNote = $this->findOwnedNote($request, $note);

        abort_unless($secretNote->cover_image, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($secretNote->cover_image), 404);

        $response = response()->file($disk->path($secretNote->cover_image), [
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
        $secretNote = $this->findOwnedNote($request, $note);
        $validated = $this->validateNote($request, false);

        if ($request->hasFile('cover_image')) {
            $this->deleteCoverImage($secretNote);
            $validated['cover_image'] = $this->storeCoverImage($request);
        }

        $secretNote->update($validated);

        return response()->json([
            'message' => __('secret_diary.note_updated'),
            'data' => $this->serializeNote($secretNote->fresh()),
        ]);
    }

    public function destroy(Request $request, string $note): JsonResponse
    {
        $secretNote = $this->findOwnedNote($request, $note);
        $this->deleteCoverImage($secretNote);
        $secretNote->delete();

        return response()->json([
            'message' => __('secret_diary.note_deleted'),
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

    private function findOwnedNote(Request $request, string $identifier): SecretDiaryNote
    {
        return $request->user()
            ->secretDiaryNotes()
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

        while ($request->user()->secretDiaryNotes()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeCoverImage(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('secret-diary-covers', 'local')
            : null;
    }

    private function deleteCoverImage(SecretDiaryNote $note): void
    {
        if ($note->cover_image) {
            Storage::disk('local')->delete($note->cover_image);
        }
    }

    private function serializeNote(SecretDiaryNote $note): array
    {
        $bodyPages = $this->paginateBody($note->body ?: __('diary.empty_body'));
        $routeIdentifier = $note->slug ?: (string) $note->id;

        return [
            'id' => $note->id,
            'slug' => $note->slug,
            'route_identifier' => $routeIdentifier,
            'entry_date' => $note->entry_date?->toDateString(),
            'formatted_date' => $note->entry_date?->translatedFormat('d F Y'),
            'title' => $note->title,
            'body' => $note->body,
            'body_pages' => $bodyPages,
            'page_count' => count($bodyPages),
            'excerpt' => Str::limit($note->body ?: __('diary.empty_excerpt'), 145),
            'photo_dedication' => $note->photo_dedication,
            'cover_image_url' => $note->coverImageUrl() ? url($note->coverImageUrl()) : null,
            'created_at' => $note->created_at?->toISOString(),
            'updated_at' => $note->updated_at?->toISOString(),
        ];
    }

    private function paginateBody(string $body): array
    {
        $normalized = trim(preg_replace("/\r\n|\r/", "\n", $body));

        if ($normalized === '') {
            return [''];
        }

        return collect(explode("\n\n", wordwrap($normalized, 1050, "\n\n", false)))
            ->map(fn (string $page): string => trim($page))
            ->filter()
            ->values()
            ->all() ?: [''];
    }
}

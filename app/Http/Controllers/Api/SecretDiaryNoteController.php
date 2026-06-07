<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecretDiaryNote;
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
            ->when($validated['date'] ?? null, fn ($query, $date) => $query->whereDate('entry_date', $date))
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
        $validated['cover_image'] = $this->storeCoverImage($request);

        $note = $request->user()->secretDiaryNotes()->create($validated);

        return response()->json([
            'message' => 'Pagina Diario Segreto creata.',
            'data' => $this->serializeNote($note),
        ], 201);
    }

    public function show(Request $request, string $note): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeNote($this->findOwnedNote($request, $note)),
        ]);
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
            'message' => 'Pagina Diario Segreto aggiornata.',
            'data' => $this->serializeNote($secretNote->fresh()),
        ]);
    }

    public function destroy(Request $request, string $note): JsonResponse
    {
        $secretNote = $this->findOwnedNote($request, $note);
        $this->deleteCoverImage($secretNote);
        $secretNote->delete();

        return response()->json([
            'message' => 'Pagina Diario Segreto eliminata.',
        ]);
    }

    private function validateNote(Request $request, bool $creating): array
    {
        return $request->validate([
            'entry_date' => [$creating ? 'required' : 'sometimes', 'date'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'body' => [$creating ? 'required' : 'sometimes', 'string', 'min:3'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'photo_dedication' => ['nullable', 'string', 'max:180'],
        ]);
    }

    private function findOwnedNote(Request $request, string $id): SecretDiaryNote
    {
        return $request->user()
            ->secretDiaryNotes()
            ->whereKey($id)
            ->firstOrFail();
    }

    private function storeCoverImage(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('secret-diary-covers', 'public')
            : null;
    }

    private function deleteCoverImage(SecretDiaryNote $note): void
    {
        if ($note->cover_image) {
            Storage::disk('public')->delete($note->cover_image);
        }
    }

    private function serializeNote(SecretDiaryNote $note): array
    {
        $bodyPages = $this->paginateBody($note->body ?: 'Questa pagina non contiene ancora testo.');

        return [
            'id' => $note->id,
            'entry_date' => $note->entry_date?->toDateString(),
            'formatted_date' => $note->entry_date?->translatedFormat('d F Y'),
            'title' => $note->title,
            'body' => $note->body,
            'body_pages' => $bodyPages,
            'page_count' => count($bodyPages),
            'excerpt' => Str::limit($note->body ?: 'Pagina ancora vuota, pronta per essere riempita.', 145),
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

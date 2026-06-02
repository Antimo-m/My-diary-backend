<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiaryNote;
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
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim((string) ($validated['q'] ?? ''));

        $notes = $request->user()
            ->diaryNotes()
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
            ->get()
            ->map(fn (DiaryNote $note): array => $this->serializeNote($note));

        return response()->json([
            'data' => $notes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateNote($request, true);
        $validated['cover_image'] = $this->storeCoverImage($request);

        $note = $request->user()->diaryNotes()->create($validated);

        return response()->json([
            'message' => 'Pagina diario creata.',
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
        $diaryNote = $this->findOwnedNote($request, $note);
        $validated = $this->validateNote($request, false);

        if ($request->hasFile('cover_image')) {
            $this->deleteCoverImage($diaryNote);
            $validated['cover_image'] = $this->storeCoverImage($request);
        }

        $diaryNote->update($validated);

        return response()->json([
            'message' => 'Pagina diario aggiornata.',
            'data' => $this->serializeNote($diaryNote->fresh()),
        ]);
    }

    public function destroy(Request $request, string $note): JsonResponse
    {
        $diaryNote = $this->findOwnedNote($request, $note);
        $this->deleteCoverImage($diaryNote);
        $diaryNote->delete();

        return response()->json([
            'message' => 'Pagina diario eliminata.',
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

    private function findOwnedNote(Request $request, string $id): DiaryNote
    {
        return $request->user()
            ->diaryNotes()
            ->whereKey($id)
            ->firstOrFail();
    }

    private function storeCoverImage(Request $request): ?string
    {
        return $request->hasFile('cover_image')
            ? $request->file('cover_image')->store('diary-covers', 'public')
            : null;
    }

    private function deleteCoverImage(DiaryNote $note): void
    {
        if ($note->cover_image) {
            Storage::disk('public')->delete($note->cover_image);
        }
    }

    private function serializeNote(DiaryNote $note): array
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

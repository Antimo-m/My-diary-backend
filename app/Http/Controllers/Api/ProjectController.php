<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);
        $validated['slug'] = $this->uniqueSlug($request, $validated['name']);

        $project = $request->user()->projects()->create($validated);

        return response()->json([
            'message' => 'Progetto creato.',
            'data' => $this->serializeProject($project),
        ], 201);
    }

    public function update(Request $request, string $project): JsonResponse
    {
        $kanbanProject = $this->findOwnedProject($request, $project);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

        $kanbanProject->update($validated);

        return response()->json([
            'message' => 'Progetto aggiornato.',
            'data' => $this->serializeProject($kanbanProject->fresh()->loadCount('tasks')),
        ]);
    }

    public function destroy(Request $request, string $project): JsonResponse
    {
        $kanbanProject = $this->findOwnedProject($request, $project);

        DB::transaction(function () use ($request, $kanbanProject): void {
            $request->user()
                ->kanbanTasks()
                ->where('project_id', $kanbanProject->id)
                ->delete();

            $request->user()
                ->kanbanColumns()
                ->where('project_id', $kanbanProject->id)
                ->delete();

            $kanbanProject->delete();
        });

        return response()->json(['message' => 'Progetto eliminato.']);
    }

    private function findOwnedProject(Request $request, string $identifier): Project
    {
        return $request->user()
            ->projects()
            ->where(function ($query) use ($identifier): void {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->firstOrFail();
    }

    private function uniqueSlug(Request $request, string $name): string
    {
        $base = Str::slug($name) ?: 'progetto';
        $slug = $base;
        $suffix = 2;

        while ($request->user()->projects()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'slug' => $project->slug,
            'route_identifier' => $project->slug ?: (string) $project->id,
            'name' => $project->name,
            'icon' => $project->icon,
            'tasks_count' => $project->tasks_count ?? null,
            'created_at' => $project->created_at?->toIso8601String(),
        ];
    }
}

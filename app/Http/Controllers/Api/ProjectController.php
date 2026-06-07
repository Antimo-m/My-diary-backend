<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

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

        $request->user()
            ->kanbanTasks()
            ->where('project_id', $kanbanProject->id)
            ->delete();

        $request->user()
            ->kanbanColumns()
            ->where('project_id', $kanbanProject->id)
            ->delete();

        $kanbanProject->delete();

        return response()->json(['message' => 'Progetto eliminato.']);
    }

    private function findOwnedProject(Request $request, string $id): Project
    {
        return $request->user()
            ->projects()
            ->whereKey($id)
            ->firstOrFail();
    }

    private function serializeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'icon' => $project->icon,
            'tasks_count' => $project->tasks_count ?? null,
            'created_at' => $project->created_at?->toIso8601String(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanTask;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatsController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['week', 'month', 'year'])],
            'board' => ['nullable', Rule::in(['all', 'daily', 'project'])],
            'project_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $period = $validated['period'] ?? 'week';
        [$board, $project] = $this->resolveBoardFilter($request, $validated);
        [$startsAt, $endsAt] = $this->periodBounds($period);

        $taskQuery = $this->kanbanTaskScope($request, $board, $project);
        $taskCounts = (clone $taskQuery)
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_completed = 1 then 1 else 0 end) as completed')
            ->first();

        $totalTasks = (int) ($taskCounts?->total ?? 0);
        $completedTasks = (int) ($taskCounts?->completed ?? 0);
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0.0;

        $diaryNotes = $user->diaryNotes()
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->count();
        $secretDiaryNotes = $user->secretDiaryNotes()
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->count();
        $diaryInteractions = $diaryNotes + $secretDiaryNotes;
        $kanbanMessageKey = match (true) {
            $completionRate < 30 => 'stats.kanban.low',
            $completionRate > 70 => 'stats.kanban.high',
            default => 'stats.kanban.stable',
        };
        $diaryMessageKey = match (true) {
            $diaryInteractions === 0 => 'stats.diary.empty',
            $diaryInteractions >= $this->diaryStrongThreshold($period) => 'stats.diary.high',
            default => 'stats.diary.stable',
        };

        $statusBreakdown = (clone $taskQuery)
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($count): int => (int) $count);

        $kanbanTrend = (clone $taskQuery)
            ->selectRaw('date(task_date) as date, count(*) as total, sum(case when is_completed = 1 then 1 else 0 end) as completed')
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (KanbanTask $row): array => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
            ]);

        $diaryTrend = $this->diaryTrend($request, $startsAt, $endsAt);

        return response()->json([
            'period' => $period,
            'board' => [
                'type' => $board,
                'project' => $project ? [
                    'id' => $project->id,
                    'name' => $project->name,
                    'icon' => $project->icon,
                ] : null,
            ],
            'range' => [
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
            ],
            'kanban' => [
                'completion_rate' => $completionRate,
                'completed_activities' => $completedTasks,
                'total_activities' => $totalTasks,
                'message_key' => $kanbanMessageKey,
                'message' => __($kanbanMessageKey),
                'status_breakdown' => [
                    KanbanTask::STATUS_TODO => $statusBreakdown->get(KanbanTask::STATUS_TODO, 0),
                    KanbanTask::STATUS_DOING => $statusBreakdown->get(KanbanTask::STATUS_DOING, 0),
                    KanbanTask::STATUS_DONE => $statusBreakdown->get(KanbanTask::STATUS_DONE, 0),
                ],
                'trend' => $kanbanTrend,
            ],
            'diary' => [
                'interactions' => $diaryInteractions,
                'public_notes' => $diaryNotes,
                'secret_notes' => $secretDiaryNotes,
                'writing_days' => $diaryTrend->where('total', '>', 0)->count(),
                'message_key' => $diaryMessageKey,
                'message' => __($diaryMessageKey),
                'trend' => $diaryTrend,
            ],
        ]);
    }

    private function resolveBoardFilter(Request $request, array $validated): array
    {
        if (($validated['board'] ?? 'all') !== 'project' && empty($validated['project_id'])) {
            return [$validated['board'] ?? 'all', null];
        }

        $project = $request->user()
            ->projects()
            ->whereKey($validated['project_id'])
            ->firstOrFail();

        return ['project', $project];
    }

    private function kanbanTaskScope(Request $request, string $board, ?Project $project)
    {
        return $request->user()
            ->kanbanTasks()
            ->when(
                $board === 'project',
                fn ($query) => $query->where('project_id', $project?->id),
                fn ($query) => $query->when($board === 'daily', fn ($dailyQuery) => $dailyQuery->whereNull('project_id')),
            );
    }

    private function periodBounds(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'year' => [$now->startOfYear(), $now->endOfYear()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            default => [$now->startOfWeek(), $now->endOfWeek()],
        };
    }

    private function diaryStrongThreshold(string $period): int
    {
        return match ($period) {
            'year' => 80,
            'month' => 12,
            default => 3,
        };
    }

    private function diaryTrend(Request $request, CarbonImmutable $startsAt, CarbonImmutable $endsAt)
    {
        $publicNotes = $request->user()
            ->diaryNotes()
            ->selectRaw('date(entry_date) as date, count(*) as total')
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $secretNotes = $request->user()
            ->secretDiaryNotes()
            ->selectRaw('date(entry_date) as date, count(*) as total')
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        return collect($publicNotes->keys())
            ->merge($secretNotes->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $date): array => [
                'date' => $date,
                'public' => (int) ($publicNotes->get($date)?->total ?? 0),
                'secret' => (int) ($secretNotes->get($date)?->total ?? 0),
                'total' => (int) ($publicNotes->get($date)?->total ?? 0) + (int) ($secretNotes->get($date)?->total ?? 0),
            ]);
    }
}

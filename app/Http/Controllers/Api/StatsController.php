<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KanbanTask;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StatsController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['week', 'month', 'year'])],
            'board' => ['nullable', Rule::in(['all', 'daily', 'project'])],
            'project_id' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:board,project',
                'prohibited_unless:board,project',
            ],
        ]);

        $user = $request->user();
        $period = $validated['period'] ?? 'week';
        [$board, $project] = $this->resolveBoardFilter($request, $validated);
        [$startsAt, $endsAt] = $this->periodBounds($period);

        $taskQuery = $this->kanbanTaskScope($request, $board, $project);
        $taskSummary = (clone $taskQuery)
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_completed = 1 then 1 else 0 end) as completed')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as todo', [KanbanTask::STATUS_TODO])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as doing', [KanbanTask::STATUS_DOING])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as done', [KanbanTask::STATUS_DONE])
            ->first();

        $totalTasks = (int) ($taskSummary?->total ?? 0);
        $completedTasks = (int) ($taskSummary?->completed ?? 0);
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0.0;

        $diaryTrend = $this->diaryTrend($user->getKey(), $startsAt, $endsAt);
        $diaryNotes = $diaryTrend->sum('public');
        $secretDiaryNotes = $diaryTrend->sum('secret');
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

        $kanbanTrend = (clone $taskQuery)
            ->selectRaw('task_date as date, count(*) as total, sum(case when is_completed = 1 then 1 else 0 end) as completed')
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('task_date')
            ->orderBy('task_date')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'date' => $row->date,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
            ]);

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
                    KanbanTask::STATUS_TODO => (int) ($taskSummary?->todo ?? 0),
                    KanbanTask::STATUS_DOING => (int) ($taskSummary?->doing ?? 0),
                    KanbanTask::STATUS_DONE => (int) ($taskSummary?->done ?? 0),
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
            ->select(['id', 'user_id', 'name', 'icon'])
            ->whereKey($validated['project_id'])
            ->firstOrFail();

        return ['project', $project];
    }

    private function kanbanTaskScope(Request $request, string $board, ?Project $project): HasMany
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

    private function diaryTrend(int $userId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Collection
    {
        $publicNotes = DB::table('diary_notes')
            ->selectRaw('entry_date as date, count(*) as public, 0 as secret')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('entry_date');

        $secretNotes = DB::table('secret_diary_notes')
            ->selectRaw('entry_date as date, 0 as public, count(*) as secret')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startsAt, $endsAt])
            ->groupBy('entry_date');

        return DB::query()
            ->fromSub($publicNotes->unionAll($secretNotes), 'diary_activity')
            ->selectRaw('date, sum(public) as public, sum(secret) as secret')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $row->date,
                'public' => (int) $row->public,
                'secret' => (int) $row->secret,
                'total' => (int) $row->public + (int) $row->secret,
            ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserReportRequest;
use App\Models\UserReport;
use App\Services\Reports\UserReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserReportController extends Controller
{
    public function store(StoreUserReportRequest $request, UserReportService $service): JsonResponse
    {
        $report = $service->submit(
            $request->validated(),
            $request->user(),
            $request->userAgent(),
            $request->ip(),
        );

        return response()->json([
            'message' => __('reports.submitted'),
            'data' => $report->only(['id', 'type', 'status', 'subject', 'created_at']),
        ], 201);
    }

    /** Le segnalazioni dell'utente autenticato, con il loro stato. */
    public function mine(Request $request): JsonResponse
    {
        $reports = $request->user()->reports()
            ->select(['id', 'type', 'status', 'subject', 'message', 'created_at'])
            ->latest()
            ->paginate(10);

        return response()->json($reports);
    }

    /** Elenco amministrativo con ricerca e filtri. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(UserReport::STATUSES)],
            'type' => ['nullable', Rule::in(UserReport::TYPES)],
        ]);

        $reports = UserReport::query()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(
                fn ($inner) => $inner->where('subject', 'like', "%{$term}%")->orWhere('message', 'like', "%{$term}%")
            ))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->with(['user:id,name,email', 'assignee:id,name'])
            ->latest()
            ->paginate(15);

        return response()->json($reports);
    }

    public function update(Request $request, UserReport $report, UserReportService $service): JsonResponse
    {
        $changes = $request->validate([
            'status' => ['sometimes', Rule::in(UserReport::STATUSES)],
            'assigned_to' => ['sometimes', 'nullable', 'exists:users,id'],
            'admin_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $updated = $service->update($report, $changes, $request->user(), $request->ip());

        return response()->json([
            'message' => __('reports.updated'),
            'data' => $updated->load(['user:id,name,email', 'assignee:id,name']),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'by_status' => UserReport::select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status'),
                'by_type' => UserReport::select('type', DB::raw('COUNT(*) as total'))->groupBy('type')->pluck('total', 'type'),
                'total' => UserReport::count(),
            ],
        ]);
    }
}

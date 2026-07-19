<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFrontendErrorRequest;
use App\Models\FrontendError;
use App\Services\FrontendMonitoring\FrontendErrorRecorder;
use App\Services\FrontendMonitoring\FrontendErrorStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FrontendErrorController extends Controller
{
    /**
     * Raccolta pubblica dei report (throttle dedicato). Risposta 204 senza
     * echo: nessun contenuto arbitrario del client torna mai in output.
     */
    public function store(StoreFrontendErrorRequest $request, FrontendErrorRecorder $recorder): Response
    {
        $recorder->record(
            $request->validated(),
            $request->user('sanctum'),
            $request->ip(),
        );

        return response()->noContent();
    }

    /**
     * Elenco per la dashboard admin: ricerca full-text semplice e filtri per
     * sorgente, browser e pagina.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'max:50'],
            'browser' => ['nullable', 'string', 'max:40'],
            'page_path' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = FrontendError::query()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('message', 'like', "%{$term}%"))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['browser'] ?? null, fn ($query, $browser) => $query->where('browser', $browser))
            ->when($filters['page_path'] ?? null, fn ($query, $page) => $query->where('page', $page))
            ->with('user:id,name,email')
            ->latest('occurred_at')
            ->paginate(15);

        return response()->json($errors);
    }

    public function stats(Request $request, FrontendErrorStats $stats): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        return response()->json([
            'data' => $stats->forPeriod((int) ($validated['days'] ?? 30)),
        ]);
    }
}

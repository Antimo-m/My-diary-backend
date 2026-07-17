<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bacheca\StoreLabelRequest;
use App\Http\Requests\Bacheca\UpdateLabelRequest;
use App\Http\Resources\KanbanLabelResource;
use App\Services\BachecaBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BachecaLabelController extends Controller
{
    public function __construct(private readonly BachecaBoardService $boardService) {}

    public function store(StoreLabelRequest $request): JsonResponse
    {
        $label = $request->user()->kanbanLabels()->create($request->validated());

        return response()->json([
            'message' => 'Etichetta creata.',
            'data' => KanbanLabelResource::make($label),
        ], 201);
    }

    public function update(UpdateLabelRequest $request, string $label): JsonResponse
    {
        $kanbanLabel = $this->boardService->findOwnedLabel($request->user(), $label);
        $kanbanLabel->update($request->validated());

        return response()->json([
            'message' => 'Etichetta aggiornata.',
            'data' => KanbanLabelResource::make($kanbanLabel->fresh()),
        ]);
    }

    public function destroy(Request $request, string $label): JsonResponse
    {
        $this->boardService->findOwnedLabel($request->user(), $label)->delete();

        return response()->json(['message' => 'Etichetta eliminata.']);
    }
}

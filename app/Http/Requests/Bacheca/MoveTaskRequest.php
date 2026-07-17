<?php

namespace App\Http\Requests\Bacheca;

use App\Models\KanbanTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kanban_column_id' => ['required', 'integer'],
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
            'position' => ['required', 'integer', 'min:0'],
            'ordered_ids' => ['nullable', 'array'],
            'ordered_ids.*' => ['integer'],
        ];
    }
}

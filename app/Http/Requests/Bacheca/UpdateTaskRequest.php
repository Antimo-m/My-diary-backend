<?php

namespace App\Http\Requests\Bacheca;

use App\Models\KanbanTask;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'task_date' => ['sometimes', 'date'],
            'project_id' => ['nullable', 'integer'],
            'kanban_column_id' => ['nullable', 'integer'],
            'title' => ['sometimes', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'reminder_option' => ['nullable', Rule::in(TaskReminderService::OPTIONS)],
            'custom_reminder_at' => ['nullable', 'required_if:reminder_option,custom', 'date'],
            'color' => ['nullable', 'string', 'max:32'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
            'status' => ['nullable', Rule::in(KanbanTask::STATUSES)],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}

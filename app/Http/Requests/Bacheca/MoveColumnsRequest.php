<?php

namespace App\Http\Requests\Bacheca;

use Illuminate\Foundation\Http\FormRequest;

class MoveColumnsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
            'project_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ];
    }
}

<?php

namespace App\Http\Requests\Bacheca;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLabelRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:40',
                Rule::unique('kanban_labels', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('label')),
            ],
            'color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
        ];
    }
}

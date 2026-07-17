<?php

namespace App\Http\Requests\Bacheca;

use Illuminate\Foundation\Http\FormRequest;

class StoreColumnRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'project_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ];
    }
}

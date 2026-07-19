<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFrontendErrorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Endpoint pubblico per design: i crash pre-login sono i piu preziosi.
        // La protezione e demandata al throttle dedicato e ai tetti qui sotto.
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'component_stack' => ['nullable', 'string', 'max:20000'],
            'source' => ['required', 'string', 'max:50'],
            'url' => ['required', 'string', 'max:2048'],
            'user_agent' => ['required', 'string', 'max:500'],
            'browser' => ['nullable', 'string', 'max:40'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'occurred_at' => ['required', 'date'],
        ];
    }
}

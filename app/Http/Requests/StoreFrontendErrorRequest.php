<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'kind' => ['nullable', Rule::in(['error', 'event'])],
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:20000'],
            'component_stack' => ['nullable', 'string', 'max:20000'],
            'source' => ['required', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'regex:/^[a-f0-9]{8,64}$/i'],
            'url' => ['required', 'string', 'max:2048'],
            'route' => ['nullable', 'string', 'max:255'],
            'user_agent' => ['required', 'string', 'max:500'],
            'browser' => ['nullable', 'string', 'max:40'],
            'os' => ['nullable', 'string', 'max:40'],
            'viewport' => ['nullable', 'string', 'max:20'],
            'language' => ['nullable', 'string', 'max:10'],
            'app_version' => ['nullable', 'string', 'max:64'],
            'commit_sha' => ['nullable', 'string', 'max:64'],
            'environment' => ['nullable', 'string', 'max:40'],
            'occurred_at' => ['required', 'date'],
            'data' => ['nullable', 'array', 'max:20'],
        ];
    }
}

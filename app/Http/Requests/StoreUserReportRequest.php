<?php

namespace App\Http\Requests;

use App\Models\UserReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo utenti autenticati (rotta sotto auth:sanctum): insieme al
        // throttle per utente e la prima difesa contro spam e flood.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(UserReport::TYPES)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'fingerprint' => ['nullable', 'string', 'regex:/^[a-f0-9]{8,64}$/i'],
            // Il contesto e informativo: viene comunque whitelistato dal
            // service, qui blocchiamo solo payload fuori misura.
            'context' => ['nullable', 'array', 'max:12'],
            'context.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}

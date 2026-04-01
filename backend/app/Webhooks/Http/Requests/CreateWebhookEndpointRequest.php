<?php

namespace App\Webhooks\Http\Requests;

use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'url'         => ['required', 'url', 'max:500'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['required', 'string', Rule::in(WebhookEventType::all())],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required'      => 'La URL del webhook es requerida.',
            'url.url'           => 'La URL del webhook no es válida.',
            'events.required'   => 'Debe seleccionar al menos un tipo de evento.',
            'events.min'        => 'Debe seleccionar al menos un tipo de evento.',
            'events.*.in'       => 'Uno o más tipos de evento no son válidos.',
        ];
    }
}

<?php

namespace App\Webhooks\Http\Requests;

use App\Webhooks\Enums\WebhookEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebhookEndpointRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'url'         => ['sometimes', 'url', 'max:500'],
            'events'      => ['sometimes', 'array', 'min:1'],
            'events.*'    => ['required_with:events', 'string', Rule::in(WebhookEventType::all())],
            'is_active'   => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.url'       => 'La URL del webhook no es válida.',
            'events.min'    => 'Debe seleccionar al menos un tipo de evento.',
            'events.*.in'   => 'Uno o más tipos de evento no son válidos.',
        ];
    }
}

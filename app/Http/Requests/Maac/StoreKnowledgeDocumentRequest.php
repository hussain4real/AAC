<?php

namespace App\Http\Requests\Maac;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'uri' => ['nullable', 'url', 'max:2048'],
            'body' => ['required', 'string', 'max:100000'],
            'metadata' => ['nullable', 'array'],
            'metadata.author' => ['nullable', 'string', 'max:255'],
            'metadata.published_at' => ['nullable', 'string', 'max:64'],
        ];
    }
}

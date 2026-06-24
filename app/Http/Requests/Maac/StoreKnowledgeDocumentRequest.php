<?php

namespace App\Http\Requests\Maac;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A document is supplied either as a pasted body or an uploaded file — one
     * is required, but not both. Uploads are constrained by the configured
     * extension allowlist and size cap; the text extractor is the real content
     * gate, so the user-assigned extension (reliable for .md/.docx) is enough
     * here.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'uri' => ['nullable', 'url', 'max:2048'],
            'body' => ['nullable', 'required_without:document', 'string', 'max:100000'],
            'document' => [
                'nullable',
                'required_without:body',
                'file',
                'extensions:'.implode(',', (array) config('maac.runtime.knowledge.upload.allowed_extensions')),
                'max:'.(int) config('maac.runtime.knowledge.upload.max_kb'),
            ],
            'metadata' => ['nullable', 'array'],
            'metadata.author' => ['nullable', 'string', 'max:255'],
            'metadata.published_at' => ['nullable', 'string', 'max:64'],
        ];
    }
}

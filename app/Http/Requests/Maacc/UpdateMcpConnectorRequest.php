<?php

namespace App\Http\Requests\Maacc;

use App\Enums\Environment;
use App\Enums\McpConnectorStatus;
use App\Enums\RemoteAuthType;
use App\Enums\Sensitivity;
use App\Rules\SafeOutboundUrl;
use App\Support\Outbound\OutboundRequestPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMcpConnectorRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(OutboundRequestPolicy $policy): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'server_url' => ['sometimes', 'required', new SafeOutboundUrl($policy, 'mcp'), 'max:2048'],
            'auth_type' => ['sometimes', 'required', Rule::enum(RemoteAuthType::class)],
            // Blank on update preserves the stored credential (write-only secret).
            'auth_credential' => ['nullable', 'string', 'max:2048'],
            'auth_header' => ['nullable', 'string', 'max:128'],
            'sensitivity' => ['sometimes', 'required', Rule::enum(Sensitivity::class)],
            'requires_approval' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in([McpConnectorStatus::Disabled->value])],
            'environments' => ['sometimes', 'required', 'array', 'min:1'],
            'environments.*' => [Rule::enum(Environment::class)],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
        ];
    }
}

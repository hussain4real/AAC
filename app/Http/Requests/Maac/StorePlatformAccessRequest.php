<?php

namespace App\Http\Requests\Maac;

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a MAAC platform-role grant (Phase 8B). The route already requires
 * the `roles.assign` permission; this additionally restricts the two most
 * sensitive grants — assigning Super Admin and activating break-glass emergency
 * access — to a Super Admin.
 */
class StorePlatformAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $role = PlatformRole::tryFrom((string) $this->input('role'));
        $kind = PlatformAccessKind::tryFrom((string) $this->input('kind', PlatformAccessKind::Standard->value));

        if ($role === PlatformRole::SuperAdmin || $kind === PlatformAccessKind::BreakGlass) {
            return $this->user()->isPlatformSuperAdmin();
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(PlatformRole::class)],
            'kind' => ['nullable', Rule::enum(PlatformAccessKind::class)],
            'reason' => ['required', 'string', 'max:1000'],
            'ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('maac.platform.break_glass.max_ttl_minutes', 240)],
        ];
    }
}

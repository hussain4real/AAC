<?php

namespace App\Http\Requests\Maacc;

use App\Enums\MaaccRole;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()->can('manageMembers', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');
        $teamId = $project instanceof Project ? $project->application()->value('team_id') : null;

        return [
            'user_id' => ['required', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $teamId)],
            'role' => ['required', Rule::enum(MaaccRole::class)->except(MaaccRole::PlatformAdmin)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}

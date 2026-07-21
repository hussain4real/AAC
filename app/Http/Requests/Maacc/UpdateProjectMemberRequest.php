<?php

namespace App\Http\Requests\Maacc;

use App\Enums\MaaccRole;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');
        $membership = $this->route('projectMember');

        return $project instanceof Project
            && $membership instanceof ProjectMember
            && $membership->project_id === $project->id
            && $this->user()->can('manageMembers', $project);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(MaaccRole::class)->except(MaaccRole::PlatformAdmin)],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}

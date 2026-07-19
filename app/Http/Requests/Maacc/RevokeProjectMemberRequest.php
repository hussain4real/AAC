<?php

namespace App\Http\Requests\Maacc;

use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Foundation\Http\FormRequest;

class RevokeProjectMemberRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000']];
    }
}

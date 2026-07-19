<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\ManageProjectMember;
use App\Enums\MaaccRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\CertifyProjectMemberRequest;
use App\Http\Requests\Maacc\RevokeProjectMemberRequest;
use App\Http\Requests\Maacc\StoreProjectMemberRequest;
use App\Http\Requests\Maacc\UpdateProjectMemberRequest;
use App\Http\Resources\Maacc\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectMemberController extends Controller
{
    public function index(Request $request, string $currentTeam, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('manageMembers', $project);

        return ProjectMemberResource::collection(
            $project->projectMembers()->with(['user', 'grantor', 'revoker', 'certifier'])->orderByDesc('created_at')->get(),
        );
    }

    public function store(StoreProjectMemberRequest $request, string $currentTeam, Project $project, ManageProjectMember $manager): JsonResponse|RedirectResponse
    {
        $user = User::query()->findOrFail($request->integer('user_id'));
        $membership = $manager->assign(
            $project,
            $user,
            MaaccRole::from($request->string('role')->value()),
            $request->user(),
            $this->expiresAt($request->validated('expires_at')),
            $request->string('reason')->value(),
        );

        return $this->respond($request, $membership, 'Project access assigned.', 201);
    }

    public function update(UpdateProjectMemberRequest $request, string $currentTeam, Project $project, ProjectMember $projectMember, ManageProjectMember $manager): JsonResponse|RedirectResponse
    {
        $membership = $manager->assign(
            $project,
            $projectMember->user,
            MaaccRole::from($request->string('role')->value()),
            $request->user(),
            $this->expiresAt($request->validated('expires_at')),
            $request->string('reason')->value(),
        );

        return $this->respond($request, $membership, 'Project access updated.');
    }

    public function revoke(RevokeProjectMemberRequest $request, string $currentTeam, Project $project, ProjectMember $projectMember, ManageProjectMember $manager): JsonResponse|RedirectResponse
    {
        $membership = $manager->revoke($projectMember, $request->user(), $request->string('reason')->value());

        return $this->respond($request, $membership, 'Project access revoked.');
    }

    public function certify(CertifyProjectMemberRequest $request, string $currentTeam, Project $project, ProjectMember $projectMember, ManageProjectMember $manager): JsonResponse|RedirectResponse
    {
        $membership = $manager->certify($projectMember, $request->user(), $request->string('note')->value());

        return $this->respond($request, $membership, 'Project access certified.');
    }

    private function expiresAt(mixed $value): ?Carbon
    {
        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    private function respond(Request $request, ProjectMember $membership, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return (new ProjectMemberResource($membership))->response()->setStatusCode($status);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

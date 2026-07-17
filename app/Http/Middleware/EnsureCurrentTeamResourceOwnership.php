<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Models\Team;
use App\Support\Governance\TenantRelationshipGuard;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentTeamResourceOwnership
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teamReference = $request->route('current_team');
        $team = $teamReference instanceof Team
            ? $teamReference
            : Team::query()->where('slug', $teamReference)->firstOrFail();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Team && $parameter->is($team)) {
                continue;
            }

            if ($parameter instanceof Model) {
                abort_unless($this->relationships->modelBelongsToTeam($parameter, $team), 404);
            }
        }

        /** @var array<string, class-string<Model>> $unboundConsoleResources */
        $unboundConsoleResources = [
            'application' => Application::class,
        ];

        foreach ($unboundConsoleResources as $parameterName => $modelClass) {
            $routeValue = $request->route($parameterName);

            if (! is_string($routeValue)) {
                continue;
            }

            $routeKey = (new $modelClass)->getRouteKeyName();
            $model = $modelClass::query()->where($routeKey, $routeValue)->first();

            if ($model instanceof Model) {
                abort_unless($this->relationships->modelBelongsToTeam($model, $team), 404);
            }
        }

        return $next($request);
    }
}

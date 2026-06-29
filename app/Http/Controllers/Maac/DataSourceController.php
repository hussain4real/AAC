<?php

namespace App\Http\Controllers\Maac;

use App\Enums\DataSourceStatus;
use App\Enums\Sensitivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreDataSourceRequest;
use App\Http\Requests\Maac\UpdateDataSourceRequest;
use App\Models\DataSource;
use App\Support\Governance\ApprovalManager;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of governed read-only data sources for `db` tools: register
 * a source (referencing an approved read-only connection by name), manage its
 * lifecycle and query surface, mark its data refreshed, and remove it. A
 * sensitive source (Confidential+) or one flagged for approval is created as a
 * draft and opens a data-source access approval; the runtime only queries an
 * active source. No connection string or credential is ever stored in plaintext.
 */
class DataSourceController extends Controller
{
    /**
     * Register a new read-only data source, gating a sensitive one behind access
     * approval.
     */
    public function store(StoreDataSourceRequest $request, ApprovalManager $approvals): RedirectResponse
    {
        Gate::authorize('create', DataSource::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $data = $request->validated();
        $sensitivity = Sensitivity::from($data['sensitivity']);
        $gated = ($data['requires_approval'] ?? false) === true || $sensitivity->requiresMasking();

        $source = new DataSource([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('data_sources', $data['name']),
            'driver' => $this->driverFor($data['connection']),
            'status' => $gated ? DataSourceStatus::Draft : DataSourceStatus::Active,
            'requires_approval' => $gated,
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $source->save();

        if ($gated) {
            $approvals->requestDataSourceAccess($source, $request->user());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $gated
                ? 'Data source registered — pending access approval before queries.'
                : 'Data source registered.',
        ]);

        return back();
    }

    /**
     * Update the given data source.
     */
    public function update(UpdateDataSourceRequest $request, string $currentTeam, DataSource $dataSource): RedirectResponse
    {
        Gate::authorize('update', $dataSource);

        $data = $request->validated();

        if (isset($data['connection'])) {
            $data['driver'] = $this->driverFor($data['connection']);
        }

        $dataSource->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data source updated.']);

        return back();
    }

    /**
     * Mark the source's data as freshly refreshed (resets the staleness clock).
     */
    public function refresh(string $currentTeam, DataSource $dataSource): RedirectResponse
    {
        Gate::authorize('update', $dataSource);

        $dataSource->update(['data_refreshed_at' => Date::now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data source marked refreshed.']);

        return back();
    }

    /**
     * Delete (soft delete) the given data source.
     */
    public function destroy(Request $request, string $currentTeam, DataSource $dataSource): RedirectResponse
    {
        Gate::authorize('delete', $dataSource);

        $dataSource->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data source removed.']);

        return back();
    }

    /**
     * Resolve the driver of the approved connection for display metadata.
     */
    private function driverFor(string $connection): ?string
    {
        $driver = config("database.connections.{$connection}.driver");

        return is_string($driver) ? $driver : null;
    }
}

<?php

namespace App\Actions\Maac;

use App\Enums\AppStatus;
use App\Models\Application;
use App\Models\Team;
use App\Support\Slug;

class CreateApplication
{
    /**
     * Register a MAAC application for the given team.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Application
    {
        return Application::create([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('applications', (string) $data['code']),
            'status' => $data['status'] ?? AppStatus::Active->value,
        ]);
    }
}

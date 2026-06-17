<?php

namespace App\Actions\Maac;

use App\Models\Project;

class ArchiveProject
{
    /**
     * Archive a MAAC project.
     */
    public function handle(Project $project): void
    {
        $project->delete();
    }
}

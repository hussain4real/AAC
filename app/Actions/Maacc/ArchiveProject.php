<?php

namespace App\Actions\Maacc;

use App\Models\Project;

class ArchiveProject
{
    /**
     * Archive a MAACC project.
     */
    public function handle(Project $project): void
    {
        $project->delete();
    }
}

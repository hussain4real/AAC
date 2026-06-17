<?php

namespace App\Actions\Maac;

use App\Models\Application;

class ArchiveApplication
{
    /**
     * Archive a MAAC application.
     */
    public function handle(Application $application): void
    {
        $application->delete();
    }
}

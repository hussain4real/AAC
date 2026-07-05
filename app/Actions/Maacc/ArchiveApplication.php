<?php

namespace App\Actions\Maacc;

use App\Models\Application;

class ArchiveApplication
{
    /**
     * Archive a MAACC application.
     */
    public function handle(Application $application): void
    {
        $application->delete();
    }
}

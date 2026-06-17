<?php

namespace App\Actions\Maac;

use App\Models\Application;

class UpdateApplication
{
    /**
     * Update a registered MAAC application.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Application $application, array $data): Application
    {
        $application->update($data);

        return $application;
    }
}

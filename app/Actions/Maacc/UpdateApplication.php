<?php

namespace App\Actions\Maacc;

use App\Models\Application;

class UpdateApplication
{
    /**
     * Update a registered MAACC application.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Application $application, array $data): Application
    {
        $application->update($data);

        return $application;
    }
}

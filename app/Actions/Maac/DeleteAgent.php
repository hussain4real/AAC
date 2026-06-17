<?php

namespace App\Actions\Maac;

use App\Models\Agent;

class DeleteAgent
{
    /**
     * Delete a MAAC agent.
     */
    public function handle(Agent $agent): void
    {
        $agent->delete();
    }
}

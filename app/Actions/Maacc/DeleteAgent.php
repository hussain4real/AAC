<?php

namespace App\Actions\Maacc;

use App\Models\Agent;

class DeleteAgent
{
    /**
     * Delete a MAACC agent.
     */
    public function handle(Agent $agent): void
    {
        $agent->delete();
    }
}

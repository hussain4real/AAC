<?php

namespace App\Actions\Maacc;

use App\Models\ToolContract;

class DeleteToolContract
{
    /**
     * Delete a MAACC tool contract.
     */
    public function handle(ToolContract $toolContract): void
    {
        $toolContract->delete();
    }
}

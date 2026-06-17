<?php

namespace App\Actions\Maac;

use App\Models\ToolContract;

class DeleteToolContract
{
    /**
     * Delete a MAAC tool contract.
     */
    public function handle(ToolContract $toolContract): void
    {
        $toolContract->delete();
    }
}

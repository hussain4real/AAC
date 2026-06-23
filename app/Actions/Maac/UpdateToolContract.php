<?php

namespace App\Actions\Maac;

use App\Models\ToolContract;
use App\Support\Tools\ToolConfigInput;

class UpdateToolContract
{
    /**
     * Update a MAAC tool contract.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(ToolContract $toolContract, array $data): ToolContract
    {
        $toolContract->update(ToolConfigInput::normalize($data, $toolContract));

        return $toolContract;
    }
}

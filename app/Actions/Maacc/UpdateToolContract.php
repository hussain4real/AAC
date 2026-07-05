<?php

namespace App\Actions\Maacc;

use App\Models\ToolContract;
use App\Support\Sdk\ContractVersionRecorder;
use App\Support\Sdk\ToolImplementationReconciler;
use App\Support\Tools\ToolConfigInput;

class UpdateToolContract
{
    public function __construct(
        private readonly ContractVersionRecorder $versions,
        private readonly ToolImplementationReconciler $reconciler,
    ) {}

    /**
     * Update a MAACC tool contract: a material change mints a new contract version
     * snapshot (auto-bumping the version), then its client-side implementations
     * are re-reconciled so any handler the change leaves behind is flagged
     * outdated/incompatible (and its application notified by webhook). A
     * cosmetic-only edit (name/description) persists without minting a version.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(ToolContract $toolContract, array $data): ToolContract
    {
        $this->versions->applyUpdate($toolContract, ToolConfigInput::normalize($data, $toolContract));

        $this->reconciler->reconcile($toolContract);

        return $toolContract;
    }
}

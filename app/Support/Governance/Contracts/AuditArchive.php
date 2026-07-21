<?php

namespace App\Support\Governance\Contracts;

use App\Models\AuditArchiveOutbox;

interface AuditArchive
{
    /** Return an immutable archive receipt. */
    public function archive(AuditArchiveOutbox $outbox): string;

    public function exists(AuditArchiveOutbox $outbox): bool;
}

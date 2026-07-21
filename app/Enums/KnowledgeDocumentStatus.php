<?php

namespace App\Enums;

enum KnowledgeDocumentStatus: string
{
    case Pending = 'pending';
    case Scanning = 'scanning';
    case Indexed = 'indexed';
    case Quarantined = 'quarantined';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Indexed, self::Quarantined, self::Failed], true);
    }
}

<?php

namespace App\Enums;

use App\Models\DataSource;

/**
 * The approved kind of read-only data surface a {@see DataSource}
 * exposes. MAAC-hosted database access is allowed only through governed
 * read-only surfaces — a read replica, a materialized view, a dedicated
 * reporting schema, or a curated set of database views — never unrestricted
 * production tables. The type is governance metadata surfaced for review.
 */
enum DbConnectionType: string
{
    case ReadReplica = 'read_replica';
    case MaterializedView = 'materialized_view';
    case ReportingSchema = 'reporting_schema';
    case CuratedView = 'curated_view';

    /**
     * Get the human-readable label for the connection type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ReadReplica => 'Read replica',
            self::MaterializedView => 'Materialized view',
            self::ReportingSchema => 'Reporting schema',
            self::CuratedView => 'Curated view',
        };
    }

    /**
     * Get all connection types as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}

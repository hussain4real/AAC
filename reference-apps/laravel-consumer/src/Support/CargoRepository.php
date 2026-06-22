<?php

declare(strict_types=1);

namespace Maac\Reference\Laravel\Support;

/**
 * Stands in for the consuming application's OWN data layer — the records here
 * never leave the application. MAAC asks for them via a client-side tool call;
 * this class is where a real app would query its database, call an internal
 * service, or apply its own row-level permissions. MAAC sees only the result.
 */
final class CargoRepository
{
    /**
     * @var array<int, array<string, string>>
     */
    private readonly array $records;

    /**
     * @param  array<int, array<string, string>>|null  $records
     */
    public function __construct(?array $records = null)
    {
        $this->records = $records ?? self::defaultManifest();
    }

    /**
     * Return the records matching a free-text query (empty query returns all).
     *
     * @return array<int, array<string, string>>
     */
    public function search(string $query): array
    {
        $needle = trim(mb_strtolower($query));

        if ($needle === '') {
            return $this->records;
        }

        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => str_contains(mb_strtolower(implode(' ', $record)), $needle),
        ));
    }

    /**
     * The application's seeded operational records.
     *
     * @return array<int, array<string, string>>
     */
    private static function defaultManifest(): array
    {
        return [
            ['vessel' => 'MV Doha', 'route' => 'Hamad → Jebel Ali', 'status' => 'on schedule'],
            ['vessel' => 'MV Lusail', 'route' => 'Hamad → Sohar', 'status' => 'delayed 2h'],
            ['vessel' => 'MV Al Wakrah', 'route' => 'Hamad → Dammam', 'status' => 'on schedule'],
        ];
    }
}

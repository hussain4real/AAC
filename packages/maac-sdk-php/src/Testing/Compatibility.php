<?php

declare(strict_types=1);

namespace Maac\Sdk\Testing;

/**
 * The client-side mirror of MAAC's tool-implementation compatibility rule. Given
 * the version + schema fingerprint a contract currently carries (from the
 * manifest) and what the application built against, it predicts the status MAAC
 * will assign — `implemented`, `outdated`, or `incompatible` — so an application
 * can detect drift locally before reporting. Kept in lock-step with the server
 * by the shared contract fixture suite (packages/sdk-fixtures).
 */
final class Compatibility
{
    public const IMPLEMENTED = 'implemented';

    public const OUTDATED = 'outdated';

    public const INCOMPATIBLE = 'incompatible';

    /**
     * Resolve the implementation status: an incompatible fingerprint wins, then
     * an older version is outdated, otherwise it is implemented.
     */
    public static function status(
        string $reportedVersion,
        string $currentVersion,
        ?string $reportedFingerprint = null,
        ?string $currentFingerprint = null,
    ): string {
        if ($reportedFingerprint !== null && $reportedFingerprint !== $currentFingerprint) {
            return self::INCOMPATIBLE;
        }

        if (version_compare($reportedVersion, $currentVersion, '<')) {
            return self::OUTDATED;
        }

        return self::IMPLEMENTED;
    }
}

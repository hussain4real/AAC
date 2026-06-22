<?php

namespace App\Support\Sdk;

use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\ToolContract;

/**
 * Computes contract schema fingerprints and reconciles an application's
 * reported client-side implementation against the current contract version.
 *
 * Compatibility is decided by two independent signals:
 *  - schema fingerprint — a stable hash of the contract's input/output shape.
 *    A mismatch means the application built against a different contract shape
 *    and the handler is {@see ImplStatus::Incompatible}.
 *  - semantic version — when the shape matches but the application reports an
 *    older contract version, the handler is {@see ImplStatus::Outdated};
 *    otherwise it is {@see ImplStatus::Implemented}.
 */
class ToolCompatibility
{
    /**
     * Compute a stable fingerprint for a pair of contract schemas.
     *
     * @param  array<string, string>  $input
     * @param  array<string, string>  $output
     */
    public static function fingerprint(array $input, array $output): string
    {
        return hash('sha256', (string) json_encode([
            'input' => self::normalize($input),
            'output' => self::normalize($output),
        ]));
    }

    /**
     * Resolve the implementation status for a reported handler against the
     * current state of its contract.
     */
    public static function evaluate(
        ToolContract $contract,
        string $reportedVersion,
        ?string $reportedFingerprint = null,
    ): ImplStatus {
        if ($contract->execution_mode !== ExecMode::Client) {
            return ImplStatus::NotApplicable;
        }

        return self::status(
            $reportedVersion,
            $contract->version,
            $reportedFingerprint,
            $contract->schemaFingerprint(),
        );
    }

    /**
     * The pure compatibility rule for a client-side contract: an incompatible
     * fingerprint wins, then an older version is outdated, otherwise it is
     * implemented. Shared by {@see self::evaluate()} and the SDK contract
     * fixtures so MAAC and every SDK language decide compatibility identically.
     */
    public static function status(
        string $reportedVersion,
        string $currentVersion,
        ?string $reportedFingerprint = null,
        ?string $currentFingerprint = null,
    ): ImplStatus {
        if ($reportedFingerprint !== null && $reportedFingerprint !== $currentFingerprint) {
            return ImplStatus::Incompatible;
        }

        if (version_compare($reportedVersion, $currentVersion, '<')) {
            return ImplStatus::Outdated;
        }

        return ImplStatus::Implemented;
    }

    /**
     * Normalize a schema map for fingerprinting: order keys deterministically
     * and collapse insignificant whitespace in type definitions.
     *
     * @param  array<string, string>  $schema
     * @return array<string, string>
     */
    private static function normalize(array $schema): array
    {
        ksort($schema);

        return array_map(
            fn (string $definition): string => str_replace(' ', '', trim($definition)),
            $schema,
        );
    }
}

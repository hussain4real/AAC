<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * A single client-side tool contract from the manifest: its current schema, the
 * fingerprint to report back when claiming an implementation, and the
 * application's current per-environment implementation status.
 */
final class ManifestTool
{
    public const STATUS_REQUIRED = 'required';

    public const STATUS_IMPLEMENTED = 'implemented';

    public const STATUS_OUTDATED = 'outdated';

    public const STATUS_INCOMPATIBLE = 'incompatible';

    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $outputSchema
     * @param  array<string, mixed>  $implementation
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $schemaFingerprint,
        public readonly array $inputSchema,
        public readonly array $outputSchema,
        public readonly array $implementation,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            schemaFingerprint: (string) ($data['schema_fingerprint'] ?? ''),
            inputSchema: is_array($data['input_schema'] ?? null) ? $data['input_schema'] : [],
            outputSchema: is_array($data['output_schema'] ?? null) ? $data['output_schema'] : [],
            implementation: is_array($data['implementation'] ?? null) ? $data['implementation'] : [],
        );
    }

    /**
     * The current implementation status MAAC holds for this tool/environment.
     */
    public function implementationStatus(): string
    {
        $status = $this->implementation['status'] ?? self::STATUS_REQUIRED;

        return is_string($status) ? $status : self::STATUS_REQUIRED;
    }

    /**
     * Whether MAAC considers this tool implemented and compatible.
     */
    public function isImplemented(): bool
    {
        return $this->implementationStatus() === self::STATUS_IMPLEMENTED;
    }
}

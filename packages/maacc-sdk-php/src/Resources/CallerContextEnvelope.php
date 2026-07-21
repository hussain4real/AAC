<?php

declare(strict_types=1);

namespace Maacc\Sdk\Resources;

/** A short-lived MAACC-signed, minimized caller-context envelope. */
final class CallerContextEnvelope
{
    /** @param  array<string, mixed>  $claims */
    public function __construct(
        public readonly string $envelope,
        public readonly array $claims,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            envelope: (string) ($data['envelope'] ?? ''),
            claims: is_array($data['claims'] ?? null) ? $data['claims'] : [],
        );
    }
}

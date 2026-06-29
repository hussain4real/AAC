<?php

namespace App\Enums;

use App\Models\VaultSecret;
use Illuminate\Support\Str;

/**
 * The category of secret material a {@see VaultSecret} holds. The vault is the
 * governed system of record for the platform's sensitive credentials: approved
 * LLM provider keys, application credential material, remote HTTP tool secrets,
 * webhook signing secrets, and MCP connector credentials. The kind drives how a
 * secret is grouped, which subject it may bind to, and how it is rotated.
 */
enum VaultSecretKind: string
{
    case LlmKey = 'llm_key';
    case Credential = 'credential';
    case HttpTool = 'http_tool';
    case Webhook = 'webhook';
    case Connector = 'connector';
    case Database = 'database';
    case Generic = 'generic';

    /**
     * Get the display label for the secret kind (e.g. "Llm Key").
     */
    public function label(): string
    {
        return match ($this) {
            self::LlmKey => 'LLM Provider Key',
            self::HttpTool => 'Remote HTTP Tool Secret',
            self::Connector => 'MCP Connector Credential',
            self::Webhook => 'Webhook Signing Secret',
            self::Credential => 'Application Credential',
            self::Database => 'Database Source Credential',
            self::Generic => 'Generic Secret',
        };
    }

    /**
     * Whether a secret of this kind binds to an approved LLM provider so the
     * runtime can resolve the provider's API key from the vault.
     */
    public function bindsToModel(): bool
    {
        return $this === self::LlmKey;
    }

    /**
     * Get all secret kinds as value/label option pairs.
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

    /**
     * Build a stable, team-unique reference key for a secret of this kind.
     */
    public function reference(string $discriminator): string
    {
        return $this->value.':'.Str::lower($discriminator);
    }
}

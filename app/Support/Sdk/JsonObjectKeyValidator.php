<?php

namespace App\Support\Sdk;

use JsonException;
use RuntimeException;

class JsonObjectKeyValidator
{
    private const MAX_DEPTH = 64;

    private int $offset = 0;

    private int $length = 0;

    /**
     * Reject malformed JSON and duplicate object keys before Laravel decodes
     * the request. Decoded key comparison also catches escaped equivalents
     * such as `"role"` and `"r\u006fle"`.
     */
    public function validate(string $json): bool
    {
        $this->offset = 0;
        $this->length = strlen($json);

        try {
            $this->parseValue($json, 0);
            $this->skipWhitespace($json);

            return $this->offset === $this->length;
        } catch (JsonException|RuntimeException) {
            return false;
        }
    }

    private function parseValue(string $json, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('JSON nesting limit exceeded.');
        }

        $this->skipWhitespace($json);
        $character = $json[$this->offset] ?? null;

        match ($character) {
            '{' => $this->parseObject($json, $depth + 1),
            '[' => $this->parseArray($json, $depth + 1),
            '"' => $this->parseString($json),
            default => $this->parseScalar($json),
        };
    }

    private function parseObject(string $json, int $depth): void
    {
        $this->offset++;
        $keys = [];
        $this->skipWhitespace($json);

        if (($json[$this->offset] ?? null) === '}') {
            $this->offset++;

            return;
        }

        while (true) {
            $this->skipWhitespace($json);

            if (($json[$this->offset] ?? null) !== '"') {
                throw new RuntimeException('Object key must be a string.');
            }

            $key = $this->parseString($json);

            if (array_key_exists($key, $keys)) {
                throw new RuntimeException('Duplicate object key.');
            }

            $keys[$key] = true;
            $this->skipWhitespace($json);

            if (($json[$this->offset] ?? null) !== ':') {
                throw new RuntimeException('Object key must be followed by a colon.');
            }

            $this->offset++;
            $this->parseValue($json, $depth);
            $this->skipWhitespace($json);
            $separator = $json[$this->offset] ?? null;

            if ($separator === '}') {
                $this->offset++;

                return;
            }

            if ($separator !== ',') {
                throw new RuntimeException('Object entries must be separated by commas.');
            }

            $this->offset++;
        }
    }

    private function parseArray(string $json, int $depth): void
    {
        $this->offset++;
        $this->skipWhitespace($json);

        if (($json[$this->offset] ?? null) === ']') {
            $this->offset++;

            return;
        }

        while (true) {
            $this->parseValue($json, $depth);
            $this->skipWhitespace($json);
            $separator = $json[$this->offset] ?? null;

            if ($separator === ']') {
                $this->offset++;

                return;
            }

            if ($separator !== ',') {
                throw new RuntimeException('Array entries must be separated by commas.');
            }

            $this->offset++;
        }
    }

    private function parseString(string $json): string
    {
        $start = $this->offset;
        $this->offset++;
        $escaped = false;

        while ($this->offset < $this->length) {
            $character = $json[$this->offset++];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            if ($character === '\\') {
                $escaped = true;

                continue;
            }

            if ($character === '"') {
                $decoded = json_decode(
                    substr($json, $start, $this->offset - $start),
                    true,
                    2,
                    JSON_THROW_ON_ERROR,
                );

                if (! is_string($decoded)) {
                    throw new RuntimeException('Invalid JSON string.');
                }

                return $decoded;
            }

            if (ord($character) < 0x20) {
                throw new RuntimeException('Unescaped control character.');
            }
        }

        throw new RuntimeException('Unterminated JSON string.');
    }

    private function parseScalar(string $json): void
    {
        $start = $this->offset;

        while ($this->offset < $this->length) {
            $character = $json[$this->offset];

            if ($character === ',' || $character === ']' || $character === '}' || ctype_space($character)) {
                break;
            }

            $this->offset++;
        }

        if ($start === $this->offset) {
            throw new RuntimeException('Expected a JSON value.');
        }

        json_decode(substr($json, $start, $this->offset - $start), true, 2, JSON_THROW_ON_ERROR);
    }

    private function skipWhitespace(string $json): void
    {
        while ($this->offset < $this->length && str_contains(" \t\n\r", $json[$this->offset])) {
            $this->offset++;
        }
    }
}

<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Protocol;

use JsonException;

/**
 * @internal A body Flitt sent, JSON or form-encoded. Flitt answers a form request with a form, and posts browser returns
 * and some callbacks as forms.
 */
final class Body
{
    /**
     * The decoded body, or null when it is neither JSON nor a form. Flitt answers a text/plain request as a form; a
     * body that says it is anything else, such as an HTML page, is never read as one.
     *
     * @return array<array-key, mixed>|null
     */
    public static function parse(string $raw, mixed $contentType): ?array
    {
        $contentType = is_string($contentType) ? strtolower($contentType) : '';
        $start = ltrim($raw)[0] ?? '';

        if (str_contains($contentType, 'json') || $start === '{' || $start === '[') {
            return self::json($raw);
        }

        $declaredForm = str_contains($contentType, 'x-www-form-urlencoded') || str_contains($contentType, 'text/plain');
        $undeclaredForm = $contentType === '' && preg_match('/^[A-Za-z0-9_.%+-]+=/', $raw) === 1;

        if ($declaredForm || $undeclaredForm) {
            return self::form($raw);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function stringKeyed(mixed $value): array
    {
        $fields = [];

        if (is_array($value)) {
            foreach ($value as $name => $field) {
                $fields[(string) $name] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private static function json(string $raw): ?array
    {
        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Read by hand rather than with parse_str(), which renames keys holding dots or spaces and builds arrays from
     * brackets. Python's urlencode writes spaces as "+", which urldecode() reads back.
     *
     * @return array<string, string>
     */
    private static function form(string $raw): array
    {
        $fields = [];

        foreach (explode('&', trim($raw)) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $fields[urldecode($name)] = urldecode($value);
        }

        return $fields;
    }
}

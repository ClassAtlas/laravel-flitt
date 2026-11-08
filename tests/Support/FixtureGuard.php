<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Protocol\Body;

/**
 * Finds what must never be committed as a fixture: another merchant, a real payer's IP address, a real card or a real
 * email address. It reads JSON, form bodies, protocol 2.0 envelopes and additional_info strings at any depth.
 */
final class FixtureGuard
{
    /**
     * Flitt's published test cards (docs.flitt.com/api/testing).
     */
    public const array TEST_CARDS = [
        '4444555566661111', '4444111166665555', '4444555511116666', '4444111155556666', '5555666644441111',
        '6666444455551111', '4444555566669999', '4444666655559999', '4444999966665555', '4444666699995555',
        '2222555566663333', '4444777799991111', '9860010099998881', '8600202020202023', '9860010088889992',
    ];

    /**
     * @return list<string> one line per problem; empty when the content is safe to commit
     */
    public static function problems(string $content): array
    {
        $problems = [];
        self::walk(json_decode($content, true, 512, JSON_THROW_ON_ERROR), '$', $problems);

        return $problems;
    }

    /**
     * @param  list<string>  $problems
     */
    private static function walk(mixed $value, string $path, array &$problems): void
    {
        if (is_string($value)) {
            $nested = self::nested($value);

            if ($nested !== null) {
                self::walk($nested, $path, $problems);
            }

            self::checkEmails($value, $path, $problems);

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            self::check((string) $key, $item, "{$path}.{$key}", $problems);
            self::walk($item, "{$path}.{$key}", $problems);
        }
    }

    /**
     * @param  list<string>  $problems
     */
    private static function check(string $key, mixed $value, string $path, array &$problems): void
    {
        if ($value === '' || $value === null || is_array($value)) {
            return;
        }

        $text = is_scalar($value) ? (string) $value : '';

        $problem = match ($key) {
            'merchant_id' => $text === (string) Live::MERCHANT_ID ? null : "merchant {$text} is not the test merchant",
            'ipaddress_v4' => str_starts_with($text, '203.0.113.') ? null : "IP {$text} is outside 203.0.113.0/24",
            'masked_card' => in_array($text, self::testCardMasks(), true) ? null : "{$text} is not a Flitt test card",
            'card_number' => in_array($text, self::TEST_CARDS, true) ? null : 'a card number that is not a Flitt test card',
            default => null,
        };

        if ($problem !== null) {
            $problems[] = "{$path}: {$problem}";
        }
    }

    /**
     * @param  list<string>  $problems
     */
    private static function checkEmails(string $value, string $path, array &$problems): void
    {
        preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', $value, $matches);

        foreach ($matches[1] as $domain) {
            if (strtolower($domain) !== 'example.com') {
                $problems[] = "{$path}: an email address outside example.com";
            }
        }
    }

    /**
     * The JSON, form or base64 JSON inside a string, or null when it holds none.
     */
    private static function nested(string $value): mixed
    {
        $trimmed = ltrim($value);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return json_decode($value, true, 512);
        }

        if (str_contains($value, '=') && str_contains($value, '&')) {
            return Body::parse($value, 'application/x-www-form-urlencoded');
        }

        if (strlen($value) > 40 && preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $value) === 1) {
            $decoded = base64_decode($value, true);

            return $decoded !== false && str_starts_with($decoded, '{') ? json_decode($decoded, true, 512) : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function testCardMasks(): array
    {
        return array_map(static fn (string $card): string => substr($card, 0, 6).'XXXXXX'.substr($card, -4), self::TEST_CARDS);
    }
}

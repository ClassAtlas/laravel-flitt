<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

/**
 * The signature vectors recorded on Flitt's public test merchant: tests/Fixtures/signature-vectors.json.
 */
final class Vectors
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        /** @var array<string, mixed> */
        return json_decode(
            (string) file_get_contents(__DIR__.'/../Fixtures/signature-vectors.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Each vector of a group, keyed by its name, for a Pest dataset.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function dataset(string $group): array
    {
        $vectors = [];

        foreach ((array) self::all()[$group] as $vector) {
            $vectors[(string) $vector['name']] = [$vector];
        }

        return $vectors;
    }
}

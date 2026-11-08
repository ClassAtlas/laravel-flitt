<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Protocol\Envelope;
use Closure;
use RuntimeException;

/**
 * The callbacks and browser returns recorded on Flitt's public test merchant: tests/Fixtures/callbacks.json and
 * tests/Fixtures/checkout-variants.json.
 */
final class Samples
{
    public const array FILES = ['callbacks.json', 'checkout-variants.json'];

    /**
     * Order fields kept in $raw only: the signature, Flitt's debugging string, a legacy field, and additional_info,
     * which AdditionalInfo reads.
     */
    public const array RAW_ONLY_ORDER_FIELDS = ['signature', 'response_signature_string', 'fee_oplata', 'additional_info'];

    /**
     * additional_info keys kept in $raw only.
     */
    public const array RAW_ONLY_ADDITIONAL_INFO = ['recurring_data'];

    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $files = [];

    /**
     * The first sample that matches.
     *
     * @param  Closure(array<string, mixed>): bool  $matches
     * @return array<string, mixed>
     */
    public static function first(Closure $matches): array
    {
        foreach (self::dataset() as [$sample]) {
            if ($matches($sample)) {
                return $sample;
            }
        }

        throw new RuntimeException('No sample matches.');
    }

    /**
     * Every sample, keyed "file: name", for a Pest dataset.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function dataset(): array
    {
        $samples = [];

        foreach (self::FILES as $file) {
            foreach (self::file($file)['samples'] as $sample) {
                $samples["{$file}: {$sample['name']}"] = [$sample];
            }
        }

        return $samples;
    }

    /**
     * @return array<string, mixed>
     */
    public static function file(string $file): array
    {
        /** @var array<string, mixed> */
        return self::$files[$file] ??= json_decode((string) file_get_contents(__DIR__.'/../Fixtures/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The order a sample carries, decoded from JSON, a form or a protocol 2.0 envelope.
     *
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    public static function fields(array $sample): array
    {
        $body = Body::parse($sample['body'], $sample['content_type']) ?? throw new RuntimeException('Unreadable sample.');

        return Envelope::is($body)
            ? (Envelope::order($body) ?? throw new RuntimeException('Unreadable envelope.'))
            : Body::stringKeyed($body);
    }
}

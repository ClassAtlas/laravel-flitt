<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\Protocol\Envelope;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\Verifier;
use RuntimeException;

/**
 * Cleans an answer recorded on the test merchant before it becomes a fixture, then signs it again with the public key.
 *
 * - additional_info.ipaddress_v4 (the caller's IP) becomes 203.0.113.10;
 * - an open-banking payer_iban, masked_iban and payer_name are replaced;
 * - emails move to example.com;
 * - this run's unique order ids become the fixed ids the fixtures hold.
 */
final class Scrubber
{
    public const string IP = '203.0.113.10';

    public const string IBAN = 'GE00XB0000000000000000';

    public const string MASKED_IBAN = 'GE00XB************0000';

    public const string PAYER_NAME = 'Test Payer';

    /**
     * @param  array<string, string>  $orderIds  ids sent in this run => the fixed ids to write instead
     */
    public static function answer(string $body, array $orderIds = []): string
    {
        $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! is_array($decoded['response'] ?? null)) {
            throw new RuntimeException('Not saving an answer the scrubber cannot read: record it again, or scrub it by hand.');
        }

        $answer = $decoded['response'];

        $decoded['response'] = match (true) {
            array_is_list($answer) => array_map(static fn (mixed $row): mixed => is_array($row) ? self::fields($row, $orderIds) : $row, $answer),
            Envelope::is($answer) => self::envelope($answer, $orderIds),
            default => self::signed($answer, $orderIds),
        };

        return json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * A flat order: scrubbed, then signed again (and its debugging string rebuilt) when Flitt had signed it.
     *
     * @param  array<array-key, mixed>  $fields
     * @param  array<string, string>  $orderIds
     * @return array<array-key, mixed>
     */
    public static function signed(array $fields, array $orderIds = []): array
    {
        $fields = self::fields($fields, $orderIds);

        if (! isset($fields['signature'])) {
            return $fields;
        }

        $key = Verifier::keyFor($fields, Live::credentials());
        $signed = array_diff_key($fields, ['signature' => true, 'response_signature_string' => true]);
        $fields['signature'] = Signer::sign($signed, $key);

        if (isset($fields['response_signature_string'])) {
            $fields['response_signature_string'] = str_replace($key, '**********', Signer::signatureString($signed, $key));
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $envelope
     * @param  array<string, string>  $orderIds
     * @return array<array-key, mixed>
     */
    private static function envelope(array $envelope, array $orderIds): array
    {
        $order = self::fields(Envelope::order($envelope) ?? [], $orderIds);
        $data = base64_encode(json_encode(['order' => $order], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

        $envelope['data'] = $data;
        $envelope['signature'] = Signer::signData($data, Verifier::keyFor($order, Live::credentials()));

        return $envelope;
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @param  array<string, string>  $orderIds
     * @return array<array-key, mixed>
     */
    private static function fields(array $fields, array $orderIds): array
    {
        foreach ($fields as $name => $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = strtr($value, $orderIds);

            if ($name === 'additional_info' && str_starts_with($value, '{')) {
                $value = self::additionalInfo($value);
            }

            $fields[$name] = self::emails($value);
        }

        return $fields;
    }

    private static function additionalInfo(string $json): string
    {
        $info = json_decode($json, true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($info)) {
            return $json;
        }

        $replacements = [
            'ipaddress_v4' => self::IP,
            'payer_iban' => self::IBAN,
            'masked_iban' => self::MASKED_IBAN,
            'payer_name' => self::PAYER_NAME,
        ];

        foreach ($replacements as $key => $replacement) {
            if (isset($info[$key]) && $info[$key] !== '') {
                $info[$key] = $replacement;
            }
        }

        return self::pythonJson($info);
    }

    /**
     * Writes additional_info the way Flitt's Python server does (json.dumps), so fixtures look like real answers.
     *
     * @param  array<array-key, mixed>  $value
     */
    private static function pythonJson(array $value): string
    {
        $parts = [];

        foreach ($value as $key => $item) {
            $parts[] = json_encode((string) $key, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).': '.(is_array($item)
                ? self::pythonJson($item)
                : json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        }

        return '{'.implode(', ', $parts).'}';
    }

    private static function emails(string $value): string
    {
        return (string) preg_replace('/([A-Za-z0-9._%+-]+)@(?!example\.com\b)[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '$1@example.com', $value);
    }
}

<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Protocol;

use JsonException;

/**
 * @internal Protocol 2.0: {"data": base64(JSON {"order": {...}}), "version": "2.0", "signature": sha1(key|data)}.
 *
 * The package never sends 2.0, but Flitt answers and calls back in 2.0 for any order created with it.
 */
final class Envelope
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function is(array $payload): bool
    {
        return is_string($payload['data'] ?? null)
            && in_array($payload['version'] ?? null, ['2.0', 2.0], true);
    }

    /**
     * The order inside the envelope, or null when the data cannot be read.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function order(array $payload): ?array
    {
        $decoded = base64_decode(is_string($payload['data'] ?? null) ? $payload['data'] : '', true);

        if ($decoded === false) {
            return null;
        }

        try {
            $data = json_decode($decoded, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            return null;
        }

        $order = is_array($data) ? ($data['order'] ?? null) : null;

        if (! is_array($order) || array_is_list($order)) {
            return null;
        }

        return Body::stringKeyed($order);
    }
}

<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Signing;

/**
 * @internal Flitt's signature: the key, then every value that is not "" or null in key order, joined with "|",
 * hashed with SHA1 as lowercase hex. "0" and 0 are kept. Protocol 2.0 signs the base64 data string instead.
 */
final class Signer
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function signatureString(array $values, string $key): string
    {
        ksort($values, SORT_STRING);

        $parts = [$key];

        foreach ($values as $value) {
            $text = PythonText::of($value);

            if ($text !== null && $text !== '') {
                $parts[] = $text;
            }
        }

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function sign(array $values, string $key): string
    {
        return sha1(self::signatureString($values, $key));
    }

    public static function signData(string $data, string $key): string
    {
        return sha1($key.'|'.$data);
    }
}

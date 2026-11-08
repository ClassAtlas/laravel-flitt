<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Protocol;

use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use ClassAtlas\Flitt\Signing\PythonText;

/**
 * @internal Reads one decoded value, tolerantly: whatever cannot be read becomes null, and the original stays in raw.
 *
 * Flitt sends the same field as a number in one body and as text in another, and "" for "not set".
 */
final class Value
{
    public const string TIMEZONE = 'Europe/Kyiv';

    public static function string(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * A whole number sent as an integer or as digits; "" and anything else is null.
     */
    public static function integer(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (! is_string($value) || preg_match('/^-?\d{1,18}$/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    public static function boolean(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            $value === 'Y', $value === 'true', $value === 'True', $value === 1, $value === '1' => true,
            $value === 'N', $value === 'false', $value === 'False', $value === 0, $value === '0' => false,
            default => null,
        };
    }

    /**
     * An amount in major units, such as 6.0 or "12.50", as an integer in minor units. The digits are read as text,
     * never through float arithmetic, and an amount with more decimals than the currency has is null.
     */
    public static function minorFromMajor(mixed $value, int $decimals = 2): ?int
    {
        $text = match (true) {
            is_int($value) => (string) $value,
            is_float($value) => PythonText::of($value),
            is_string($value) => trim($value),
            default => null,
        };

        if ($text === null || preg_match('/^(-?)(\d{1,15})(?:\.(\d+))?$/', $text, $parts) !== 1) {
            return null;
        }

        $fraction = rtrim($parts[3] ?? '', '0');

        if (strlen($fraction) > $decimals) {
            return null;
        }

        $minor = (int) ($parts[2].str_pad($fraction, $decimals, '0'));

        return $parts[1] === '-' ? -$minor : $minor;
    }

    /**
     * Flitt's local time without an offset ("08.11.2026 14:08:28", or "08.11.2026" for a day), read as Europe/Kyiv
     * time, which Flitt's servers have used so far.
     */
    public static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $format = strlen($value) === 10 ? 'd.m.Y' : 'd.m.Y H:i:s';
        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, self::TIMEZONE);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format($format) === $value ? $date : null;
    }

    /**
     * The case of a string-backed enum, or null for a value this version does not know.
     *
     * @template TEnum of BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum|null
     */
    public static function enum(string $enum, mixed $value): ?BackedEnum
    {
        return is_string($value) ? $enum::tryFrom($value) : null;
    }
}

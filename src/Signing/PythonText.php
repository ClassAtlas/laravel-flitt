<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Signing;

use BackedEnum;
use InvalidArgumentException;

/**
 * @internal Turns a decoded value into the text Flitt's Python server signs: str() of the value.
 *
 * Flitt's real bodies only carry strings and integers at the top level. Booleans and floats follow Python 3's rules
 * (True, 6.0, 1e+16), which Python 2 shares for any float of up to 12 significant digits.
 */
final class PythonText
{
    /**
     * The text, or null for a value Flitt leaves out (null).
     *
     * @throws InvalidArgumentException for arrays and objects, which Flitt never signs as plain values
     */
    public static function of(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_bool($value) => $value ? 'True' : 'False',
            is_float($value) => self::float($value),
            $value instanceof BackedEnum => (string) $value->value,
            default => throw new InvalidArgumentException('Only strings, numbers and booleans can be signed.'),
        };
    }

    /**
     * Python's repr() of a float: the shortest digits that read back as the same number, in fixed notation from
     * 1e-4 up to 1e16, and in scientific notation outside it.
     */
    private static function float(float $value): string
    {
        if (is_nan($value)) {
            return 'nan';
        }

        if (is_infinite($value)) {
            return $value > 0 ? 'inf' : '-inf';
        }

        $sign = $value < 0 || ($value === 0.0 && fdiv(1, $value) < 0) ? '-' : '';
        $magnitude = abs($value);

        if ($magnitude === 0.0) {
            return $sign.'0.0';
        }

        $precision = 0;

        do {
            $scientific = sprintf('%.'.$precision.'e', $magnitude);
            $precision++;
        } while ((float) $scientific !== $magnitude && $precision < 17);

        [$mantissa, $exponent] = explode('e', $scientific);
        $exponent = (int) $exponent;
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        if ($exponent < -4 || $exponent >= 16) {
            $fraction = substr($digits, 1);

            return $sign.$digits[0].($fraction === '' ? '' : '.'.$fraction)
                .'e'.($exponent < 0 ? '-' : '+').str_pad((string) abs($exponent), 2, '0', STR_PAD_LEFT);
        }

        if ($exponent < 0) {
            return $sign.'0.'.str_repeat('0', -$exponent - 1).$digits;
        }

        $whole = str_pad($digits, $exponent + 1, '0');
        $fraction = substr($whole, $exponent + 1);

        return $sign.substr($whole, 0, $exponent + 1).'.'.($fraction === '' ? '0' : $fraction);
    }
}

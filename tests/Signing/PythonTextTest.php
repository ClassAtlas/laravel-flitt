<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Signing\PythonText;
use ClassAtlas\Flitt\Signing\Signer;

it('writes each value as Flitt\'s Python server does', function (mixed $value, ?string $text) {
    expect(PythonText::of($value))->toBe($text);
})->with([
    'a string, byte for byte' => [' Tuition, ნოემბერი ', ' Tuition, ნოემბერი '],
    'an empty string' => ['', ''],
    'the string "0"' => ['0', '0'],
    'an integer' => [1549901, '1549901'],
    'zero' => [0, '0'],
    'a negative integer' => [-5, '-5'],
    'true' => [true, 'True'],
    'false' => [false, 'False'],
    'null, which is left out' => [null, null],
    'a whole float' => [6.0, '6.0'],
    'zero as a float' => [0.0, '0.0'],
    'negative zero' => [-0.0, '-0.0'],
    'a half' => [0.5, '0.5'],
    'ten' => [10.0, '10.0'],
    'a negative float' => [-2.5, '-2.5'],
    'the smallest fixed-notation float' => [0.0001, '0.0001'],
    'below that, scientific' => [0.000015, '1.5e-05'],
    'the largest fixed-notation float' => [1e15, '1000000000000000.0'],
    'from 1e16, scientific' => [1e16, '1e+16'],
    'a large float' => [1.5e22, '1.5e+22'],
    'twelve significant digits' => [123456789.123, '123456789.123'],
    'the shortest digits that read back' => [0.1 + 0.2, '0.30000000000000004'],
]);

it('refuses to turn arrays into text, since Flitt never signs them as plain values', function () {
    PythonText::of(['nested' => 'value']);
})->throws(InvalidArgumentException::class);

it('signs synthetic booleans and decimals the same way', function () {
    expect(Signer::signatureString([
        'preauth' => true,
        'capture_amount' => 6.0,
        'client_fee' => 0.5,
        'verification' => false,
        'merchant_data' => null,
    ], 'test'))->toBe('test|6.0|0.5|True|False');
});

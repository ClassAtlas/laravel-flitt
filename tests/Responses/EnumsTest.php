<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Enums\CaptureStatus;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\Lang;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\PaymentMethod;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Enums\ReverseStatus;
use ClassAtlas\Flitt\Enums\TranType;

it('is backed by Flitt\'s own values', function (string $enum, array $values) {
    expect(array_map(static fn (BackedEnum $case): string|int => $case->value, $enum::cases()))->toBe($values);
})->with([
    'OrderStatus' => [OrderStatus::class, ['created', 'processing', 'declined', 'approved', 'expired', 'reversed']],
    'TranType' => [TranType::class, ['purchase', 'verification', 'capture', 'reverse', 'p2p credit', 'p2p transfer', 'settlement']],
    'CaptureStatus' => [CaptureStatus::class, ['hold', 'captured']],
    'ReverseStatus' => [ReverseStatus::class, ['created', 'processing', 'approved', 'declined']],
    'Currency' => [Currency::class, ['GEL', 'USD', 'EUR', 'AMD', 'AZN', 'KZT', 'MDL', 'UZS', 'GBP', 'CZK']],
    'Lang' => [Lang::class, ['az', 'cs', 'da', 'de', 'en', 'es', 'fi', 'fr', 'hu', 'it', 'ka', 'ko', 'lv', 'nl', 'pl', 'ro', 'ru', 'sk', 'uk', 'zh']],
    'PaymentSystem' => [PaymentSystem::class, ['card', 'opb', 'installments', 'p2p_iban_ge']],
    'PaymentMethod' => [PaymentMethod::class, ['tbc', 'bog', 'liberty', 'credo', 'x']],
]);

it('names every case after its value, in SCREAMING_SNAKE_CASE', function () {
    foreach ([OrderStatus::class, TranType::class, CaptureStatus::class, ReverseStatus::class, Currency::class, Lang::class, PaymentSystem::class] as $enum) {
        foreach ($enum::cases() as $case) {
            expect($case->name)->toBe(strtoupper(str_replace(' ', '_', (string) $case->value)));
        }
    }

    expect(PaymentMethod::DEMO->value)->toBe('x');
});

it('gives every currency two decimals', function () {
    foreach (Currency::cases() as $currency) {
        expect($currency->decimals())->toBe(2);
    }
});

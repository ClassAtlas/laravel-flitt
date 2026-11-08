<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Enums\CaptureStatus;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Enums\TranType;
use ClassAtlas\Flitt\Responses\AdditionalInfo;
use ClassAtlas\Flitt\Responses\Order;
use ClassAtlas\Flitt\Tests\Support\Samples;
use Illuminate\Support\Str;

/**
 * Flitt fields the Order leaves in $raw only: the signature, Flitt's debugging string, and legacy or per-operation
 * fields that are not part of the order.
 */
const RAW_ONLY_ORDER_FIELDS = ['signature', 'response_signature_string', 'fee_oplata', 'additional_info'];

/**
 * additional_info keys AdditionalInfo leaves in $raw only.
 */
const RAW_ONLY_ADDITIONAL_INFO = ['recurring_data'];

/**
 * @return array<string, mixed>
 */
function firstSample(Closure $matches): array
{
    foreach (Samples::dataset() as [$sample]) {
        if ($matches($sample)) {
            return $sample;
        }
    }

    throw new RuntimeException('No sample matches.');
}

it('reads every recorded callback and return into a fully typed order', function (array $sample) {
    $fields = Samples::fields($sample);
    $order = Order::fromFields($fields);

    expect($order->merchantId)->toBe(1549901)
        ->and($order->orderId)->not->toBeEmpty()
        ->and($order->amount)->toBeGreaterThan(0)
        ->and($order->currency)->toBe(Currency::GEL)
        ->and($order->orderTime)->toBeInstanceOf(CarbonImmutable::class)
        ->and($order->orderStatus?->value)->toBe($sample['order_status'])
        ->and($order->raw)->toBe($fields);

    foreach (array_diff_key($fields, array_flip(RAW_ONLY_ORDER_FIELDS)) as $field => $value) {
        $property = Str::camel($field);

        expect(property_exists($order, $property))->toBeTrue("Order has no property for {$field}.");

        if ($value !== '' && $value !== null) {
            expect($order->{$property})->not->toBeNull("{$field} = ".json_encode($value).' was lost.');
        }
    }

    foreach (array_diff_key($order->additionalInfo->raw, array_flip(RAW_ONLY_ADDITIONAL_INFO)) as $key => $value) {
        $property = Str::camel($key);

        expect(property_exists($order->additionalInfo, $property))->toBeTrue("AdditionalInfo has no property for {$key}.");

        if ($value !== '' && $value !== null) {
            expect($order->additionalInfo->{$property})->not->toBeNull("additional_info.{$key} = ".json_encode($value).' was lost.');
        }
    }
})->with(Samples::dataset());

it('reads all 59 recorded samples', function () {
    expect(Samples::dataset())->toHaveCount(59);
});

it('reads an approved card payment field by field', function () {
    $sample = firstSample(static fn (array $s): bool => $s['channel'] === 'server_callback_url'
        && $s['order_status'] === 'approved'
        && str_contains($s['body'], '"payment_system": "card"'));

    $order = Order::fromFields(Samples::fields($sample));

    expect($order)
        ->orderStatus->toBe(OrderStatus::APPROVED)
        ->tranType->toBe(TranType::PURCHASE)
        ->paymentSystem->toBe(PaymentSystem::CARD)
        ->maskedCard->toMatch('/^\d{6}X{6}\d{4}$/')
        ->cardBin->toBeInt()
        ->paymentId->toBeInt()
        ->actualAmount->toBe($order->amount)
        ->and($order->additionalInfo)
        ->transactionId->toBeInt()
        ->isTest->toBeTrue()
        ->ipaddressV4->toBe('203.0.113.10');
});

it('reads a payer\'s IBAN and name from an open-banking payment', function () {
    $sample = firstSample(static fn (array $s): bool => $s['encoding'] === 'json' && str_contains($s['body'], 'payer_iban'));

    $order = Order::fromFields(Samples::fields($sample));

    expect($order->paymentSystem)->toBe(PaymentSystem::OPB)
        ->and($order->additionalInfo)
        ->payerIban->toStartWith('GE')
        ->payerName->not->toBeEmpty()
        ->maskedIban->toContain('*')
        ->paymentMethod->toBe('X');
});

it('keeps an unknown status, an unknown enum value and an unknown field without throwing', function () {
    $order = Order::fromFields([
        'order_id' => 'inv-1042',
        'merchant_id' => 1549901,
        'order_status' => 'refunding',
        'tran_type' => 'cashback',
        'currency' => 'JPY',
        'payment_system' => 'crypto',
        'amount' => '12500',
        'brand_new_field' => 'whatever',
        'additional_info' => '{"capture_status": "partially_captured", "new_key": 1}',
    ]);

    expect($order)
        ->orderStatus->toBeNull()
        ->tranType->toBeNull()
        ->currency->toBeNull()
        ->paymentSystem->toBeNull()
        ->and($order->raw)->toMatchArray([
            'order_status' => 'refunding',
            'tran_type' => 'cashback',
            'currency' => 'JPY',
            'brand_new_field' => 'whatever',
        ])
        ->and($order->additionalInfo->captureStatus)->toBeNull()
        ->and($order->additionalInfo->raw)->toBe(['capture_status' => 'partially_captured', 'new_key' => 1]);
});

it('reads ids and codes as integers, however Flitt sends them', function (mixed $value, ?int $expected) {
    $order = Order::fromFields(['order_id' => 'x', 'payment_id' => $value, 'card_bin' => $value, 'response_code' => $value]);

    expect($order)->paymentId->toBe($expected)->cardBin->toBe($expected)->responseCode->toBe($expected);
})->with([
    'an integer' => [1029157899, 1029157899],
    'digits' => ['1029157899', 1029157899],
    'empty' => ['', null],
    'missing' => [null, null],
    'not a number' => ['12ab', null],
]);

it('reads minor-unit amounts, whether text or numbers', function () {
    $order = Order::fromFields([
        'order_id' => 'x',
        'amount' => '12500',
        'actual_amount' => '',
        'reversal_amount' => 2000,
        'settlement_amount' => '0',
        'fee' => '',
    ]);

    expect($order)
        ->amount->toBe(12_500)
        ->actualAmount->toBeNull()
        ->reversalAmount->toBe(2000)
        ->settlementAmount->toBe(0)
        ->fee->toBeNull();
});

it('converts additional_info\'s major-unit amounts to minor units exactly', function (string $json, ?int $captureAmount) {
    expect(AdditionalInfo::fromJson($json, Currency::GEL)->captureAmount)->toBe($captureAmount);
})->with([
    'a whole amount' => ['{"capture_amount": 6.0}', 600],
    'tetri' => ['{"capture_amount": 0.6}', 60],
    'two decimals' => ['{"capture_amount": 1234.57}', 123_457],
    'one that floats get wrong' => ['{"capture_amount": 0.29}', 29],
    'large' => ['{"capture_amount": 99999999.99}', 9_999_999_999],
    'an integer' => ['{"capture_amount": 10}', 1000],
    'text' => ['{"capture_amount": "12.50"}', 1250],
    'more decimals than GEL has' => ['{"capture_amount": 0.005}', null],
    'null' => ['{"capture_amount": null}', null],
    'missing' => ['{}', null],
]);

it('leaves a major-unit amount unconverted when the currency is unknown', function () {
    expect(AdditionalInfo::fromJson('{"capture_amount": 6.0}', null))
        ->captureAmount->toBeNull()
        ->raw->toBe(['capture_amount' => 6.0]);
});

it('treats additional_info that is not JSON as empty, without throwing', function (mixed $json) {
    expect(AdditionalInfo::fromJson($json, Currency::GEL))
        ->transactionId->toBeNull()
        ->raw->toBe([]);
})->with(['{broken', '', null, '"a string"']);

it('reads Flitt\'s local times as Europe/Kyiv time, summer and winter', function (string $text, string $utc) {
    $order = Order::fromFields(['order_id' => 'x', 'order_time' => $text]);

    expect($order->orderTime?->getTimezone()->getName())->toBe('Europe/Kyiv')
        ->and($order->orderTime?->utc()->format('Y-m-d H:i:s'))->toBe($utc)
        ->and($order->raw['order_time'])->toBe($text);
})->with([
    'winter, UTC+2' => ['08.11.2026 14:08:28', '2026-11-08 12:08:28'],
    'summer, UTC+3' => ['15.07.2026 14:08:28', '2026-07-15 11:08:28'],
]);

it('reads a settlement date as the start of that day', function () {
    expect(Order::fromFields(['order_id' => 'x', 'settlement_date' => '09.11.2026'])->settlementDate?->format('Y-m-d H:i:s T'))
        ->toBe('2026-11-09 00:00:00 EET');
});

it('leaves a date it cannot read as null', function (string $text) {
    expect(Order::fromFields(['order_id' => 'x', 'order_time' => $text])->orderTime)->toBeNull();
})->with(['32.13.2026 25:61:61', '2026-11-08 14:08:28', '07/02/2016 15:21:39', 'soon']);

it('reads the capture status of a hold', function () {
    expect(AdditionalInfo::fromJson('{"capture_status": "hold", "capture_amount": 0.0}', Currency::GEL))
        ->captureStatus->toBe(CaptureStatus::HOLD)
        ->captureAmount->toBe(0);
});

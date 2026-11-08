<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Testing;

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\TranType;
use ClassAtlas\Flitt\Protocol\Value;

/**
 * An order for tests: a prepared answer for Flitt::fake(), or the content of a FakeCallback. Every method returns a new
 * builder. Anything not set is filled from the request it answers, or with a plain default.
 */
final class FakeOrder
{
    private ?string $orderId = null;

    private ?int $merchantId = null;

    private ?int $amount = null;

    private ?Currency $currency = null;

    private ?int $transactionId = null;

    private ?string $rectoken = null;

    private ?string $merchantData = null;

    private ?int $responseCode = null;

    private ?string $responseDescription = null;

    private TranType $tranType = TranType::PURCHASE;

    private function __construct(private OrderStatus $orderStatus) {}

    public static function approved(): self
    {
        return new self(OrderStatus::APPROVED);
    }

    /**
     * A declined payment, with Flitt's decline code, such as 1097 (declined by the bank).
     */
    public static function declined(int $responseCode = 1097, string $responseDescription = 'Payment declined'): self
    {
        $order = new self(OrderStatus::DECLINED);
        $order->responseCode = $responseCode;
        $order->responseDescription = $responseDescription;

        return $order;
    }

    public static function processing(): self
    {
        return new self(OrderStatus::PROCESSING);
    }

    public static function created(): self
    {
        return new self(OrderStatus::CREATED);
    }

    public static function expired(): self
    {
        return new self(OrderStatus::EXPIRED);
    }

    public static function reversed(): self
    {
        return new self(OrderStatus::REVERSED);
    }

    public function orderId(string $orderId): self
    {
        $order = clone $this;
        $order->orderId = $orderId;

        return $order;
    }

    public function merchantId(int $merchantId): self
    {
        $order = clone $this;
        $order->merchantId = $merchantId;

        return $order;
    }

    /**
     * @param  int  $amount  in minor units
     */
    public function amount(int $amount): self
    {
        $order = clone $this;
        $order->amount = $amount;

        return $order;
    }

    public function currency(Currency $currency): self
    {
        $order = clone $this;
        $order->currency = $currency;

        return $order;
    }

    /**
     * The id of this event; a retried callback repeats it.
     */
    public function transactionId(int $transactionId): self
    {
        $order = clone $this;
        $order->transactionId = $transactionId;

        return $order;
    }

    /**
     * The saved card's token, as a first payment with requiredRectoken() returns it.
     */
    public function rectoken(string $rectoken): self
    {
        $order = clone $this;
        $order->rectoken = $rectoken;

        return $order;
    }

    public function merchantData(string $merchantData): self
    {
        $order = clone $this;
        $order->merchantData = $merchantData;

        return $order;
    }

    /**
     * A payout order (tran_type "p2p credit"), which Flitt signs with the credit key.
     */
    public function payout(): self
    {
        $order = clone $this;
        $order->tranType = TranType::P2P_CREDIT;

        return $order;
    }

    /**
     * @internal The order as Flitt would send it, unsigned. Values the builder lacks come from $defaults, Flitt's names.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, string|int>
     */
    public function fields(array $defaults = []): array
    {
        $orderId = $this->orderId ?? self::text($defaults['order_id'] ?? null) ?? 'fake-order';
        $merchantId = $this->merchantId ?? (is_int($defaults['merchant_id'] ?? null) ? $defaults['merchant_id'] : self::configuredMerchantId());
        $amount = $this->amount ?? (is_int($defaults['amount'] ?? null) ? $defaults['amount'] : 100);
        $currency = $this->currency->value ?? self::text($defaults['currency'] ?? null) ?? Currency::GEL->value;
        $rectoken = $this->rectoken ?? self::text($defaults['rectoken'] ?? null) ?? '';
        $paid = $this->orderStatus === OrderStatus::APPROVED || $this->orderStatus === OrderStatus::REVERSED;
        $transactionId = $this->transactionId ?? abs(crc32($orderId.$this->orderStatus->value));
        $now = CarbonImmutable::now(Value::TIMEZONE)->format('d.m.Y H:i:s');

        return [
            'order_id' => $orderId,
            'merchant_id' => $merchantId,
            'amount' => (string) $amount,
            'currency' => $currency,
            'order_status' => $this->orderStatus->value,
            'response_status' => 'success',
            'tran_type' => $this->tranType->value,
            'payment_id' => abs(crc32('payment|'.$orderId)),
            'order_time' => $now,
            'actual_amount' => $paid ? (string) $amount : '',
            'actual_currency' => $paid ? $currency : '',
            'reversal_amount' => $this->orderStatus === OrderStatus::REVERSED ? (string) $amount : '0',
            'settlement_amount' => '0',
            'settlement_currency' => '',
            'settlement_date' => '',
            'fee' => '',
            'masked_card' => $paid ? '444455XXXXXX6666' : '',
            'card_bin' => $paid ? 444455 : '',
            'card_type' => $paid ? 'VISA' : '',
            'payment_system' => $paid ? 'card' : '',
            'approval_code' => $this->orderStatus === OrderStatus::APPROVED ? '123456' : '',
            'rrn' => $paid ? '111111111111' : '',
            'eci' => $paid ? '7' : '',
            'response_code' => $this->responseCode ?? '',
            'response_description' => $this->responseDescription ?? '',
            'sender_email' => '',
            'sender_cell_phone' => '',
            'sender_account' => '',
            'product_id' => '',
            'merchant_data' => $this->merchantData ?? self::text($defaults['merchant_data'] ?? null) ?? '',
            'rectoken' => $rectoken,
            'rectoken_lifetime' => $rectoken === '' ? '' : '01.01.2031 00:00:00',
            'verification_status' => '',
            'parent_order_id' => '',
            'additional_info' => json_encode([
                'transaction_id' => $transactionId,
                'capture_status' => null,
                'capture_amount' => null,
                'bank_response_code' => null,
                'payment_method' => $paid ? 'card' : null,
                'timeend' => $now,
                'ipaddress_v4' => '203.0.113.10',
                'is_test' => true,
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function configuredMerchantId(): int
    {
        $merchantId = function_exists('config') ? config('flitt.merchant_id') : null;

        return is_numeric($merchantId) && (int) $merchantId > 0 ? (int) $merchantId : 1549901;
    }
}

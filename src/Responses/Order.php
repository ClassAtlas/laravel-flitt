<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Responses;

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\OrderStatus;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Enums\TranType;
use ClassAtlas\Flitt\Protocol\Value;

/**
 * A Flitt order, as status, recurring charges, payouts, callbacks and browser returns all return it.
 *
 * Properties are Flitt's order fields in camelCase. Amounts are integers in minor units; dates are read as
 * Europe/Kyiv time. A value this version does not know is null, and $raw keeps everything Flitt sent.
 *
 * orderStatus alone does not say everything: a partial reverse keeps APPROVED, and so does a hold. Read
 * reversalAmount, settlementAmount and additionalInfo->captureStatus beside it.
 */
final readonly class Order
{
    /**
     * @internal Only the package creates response objects.
     *
     * @param  array<string, mixed>  $raw  everything Flitt sent, including the signature
     */
    public function __construct(
        public string $orderId,
        public int $merchantId,
        public ?OrderStatus $orderStatus,
        public ?TranType $tranType,
        public int $amount,
        public ?Currency $currency,
        public ?int $actualAmount,
        public ?Currency $actualCurrency,
        public int $reversalAmount,
        public int $settlementAmount,
        public ?Currency $settlementCurrency,
        public ?CarbonImmutable $settlementDate,
        public ?int $fee,
        public ?CarbonImmutable $orderTime,
        public ?int $paymentId,
        public ?PaymentSystem $paymentSystem,
        public ?string $maskedCard,
        public ?int $cardBin,
        public ?string $cardType,
        public ?string $rrn,
        public ?string $approvalCode,
        public ?string $eci,
        public ?int $responseCode,
        public ?string $responseDescription,
        public ?string $responseStatus,
        public ?string $senderEmail,
        public ?string $senderCellPhone,
        public ?string $senderAccount,
        public ?string $productId,
        public ?string $merchantData,
        public ?string $rectoken,
        public ?CarbonImmutable $rectokenLifetime,
        public ?string $verificationStatus,
        public ?string $parentOrderId,
        public AdditionalInfo $additionalInfo,
        public array $raw,
    ) {}

    /**
     * @internal
     *
     * @param  array<string, mixed>  $fields  a flat order, as the reader returns it
     */
    public static function fromFields(array $fields): self
    {
        $currency = Value::enum(Currency::class, $fields['currency'] ?? null);

        return new self(
            orderId: Value::string($fields['order_id'] ?? null) ?? '',
            merchantId: Value::integer($fields['merchant_id'] ?? null) ?? 0,
            orderStatus: Value::enum(OrderStatus::class, $fields['order_status'] ?? null),
            tranType: Value::enum(TranType::class, $fields['tran_type'] ?? null),
            amount: Value::integer($fields['amount'] ?? null) ?? 0,
            currency: $currency,
            actualAmount: Value::integer($fields['actual_amount'] ?? null),
            actualCurrency: Value::enum(Currency::class, $fields['actual_currency'] ?? null),
            reversalAmount: Value::integer($fields['reversal_amount'] ?? null) ?? 0,
            settlementAmount: Value::integer($fields['settlement_amount'] ?? null) ?? 0,
            settlementCurrency: Value::enum(Currency::class, $fields['settlement_currency'] ?? null),
            settlementDate: Value::date($fields['settlement_date'] ?? null),
            fee: Value::integer($fields['fee'] ?? null),
            orderTime: Value::date($fields['order_time'] ?? null),
            paymentId: Value::integer($fields['payment_id'] ?? null),
            paymentSystem: Value::enum(PaymentSystem::class, $fields['payment_system'] ?? null),
            maskedCard: Value::string($fields['masked_card'] ?? null),
            cardBin: Value::integer($fields['card_bin'] ?? null),
            cardType: Value::string($fields['card_type'] ?? null),
            rrn: Value::string($fields['rrn'] ?? null),
            approvalCode: Value::string($fields['approval_code'] ?? null),
            eci: Value::string($fields['eci'] ?? null),
            responseCode: Value::integer($fields['response_code'] ?? null),
            responseDescription: Value::string($fields['response_description'] ?? null),
            responseStatus: Value::string($fields['response_status'] ?? null),
            senderEmail: Value::string($fields['sender_email'] ?? null),
            senderCellPhone: Value::string($fields['sender_cell_phone'] ?? null),
            senderAccount: Value::string($fields['sender_account'] ?? null),
            productId: Value::string($fields['product_id'] ?? null),
            merchantData: Value::string($fields['merchant_data'] ?? null),
            rectoken: Value::string($fields['rectoken'] ?? null),
            rectokenLifetime: Value::date($fields['rectoken_lifetime'] ?? null),
            verificationStatus: Value::string($fields['verification_status'] ?? null),
            parentOrderId: Value::string($fields['parent_order_id'] ?? null),
            additionalInfo: AdditionalInfo::fromJson($fields['additional_info'] ?? null, $currency),
            raw: $fields,
        );
    }
}

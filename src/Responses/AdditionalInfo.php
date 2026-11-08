<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Responses;

use Carbon\CarbonImmutable;
use ClassAtlas\Flitt\Enums\CaptureStatus;
use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Protocol\Value;
use JsonException;

/**
 * The order's additional_info, which Flitt sends as a JSON string. Amounts are in minor units.
 *
 * An open-banking payment carries the payer's IBAN and name here: treat them as personal data.
 */
final readonly class AdditionalInfo
{
    /**
     * @internal Only the package creates response objects.
     *
     * @param  array<string, mixed>  $raw  everything Flitt sent, decoded
     */
    public function __construct(
        public ?int $transactionId,
        public ?CaptureStatus $captureStatus,
        public ?int $captureAmount,
        public ?string $bankResponseCode,
        public ?string $bankResponseDescription,
        public ?string $bankName,
        public ?string $bankCountry,
        public ?string $cardType,
        public ?string $cardProduct,
        public ?string $cardCategory,
        public ?int $clientFee,
        public ?int $settlementFee,
        public ?CarbonImmutable $timeend,
        public ?string $paymentMethod,
        public ?string $reservationData,
        public ?string $ipaddressV4,
        public ?string $schemeid,
        public ?string $acqTranId,
        public ?int $version3ds,
        public ?bool $isTest,
        public ?string $maskedIban,
        public ?string $payerIban,
        public ?string $payerName,
        public array $raw,
    ) {}

    /**
     * @internal
     *
     * @param  mixed  $json  the additional_info string as Flitt sent it
     * @param  Currency|null  $currency  the order's currency, which its amounts are in
     */
    public static function fromJson(mixed $json, ?Currency $currency): self
    {
        $raw = [];

        if (is_string($json) && $json !== '') {
            try {
                $raw = Body::stringKeyed(json_decode($json, true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
            } catch (JsonException) {
                $raw = [];
            }
        }

        $minor = static fn (string $key): ?int => $currency === null
            ? null
            : Value::minorFromMajor($raw[$key] ?? null, $currency->decimals());

        return new self(
            transactionId: Value::integer($raw['transaction_id'] ?? null),
            captureStatus: Value::enum(CaptureStatus::class, $raw['capture_status'] ?? null),
            captureAmount: $minor('capture_amount'),
            bankResponseCode: Value::string($raw['bank_response_code'] ?? null),
            bankResponseDescription: Value::string($raw['bank_response_description'] ?? null),
            bankName: Value::string($raw['bank_name'] ?? null),
            bankCountry: Value::string($raw['bank_country'] ?? null),
            cardType: Value::string($raw['card_type'] ?? null),
            cardProduct: Value::string($raw['card_product'] ?? null),
            cardCategory: Value::string($raw['card_category'] ?? null),
            clientFee: $minor('client_fee'),
            settlementFee: $minor('settlement_fee'),
            timeend: Value::date($raw['timeend'] ?? null),
            paymentMethod: Value::string($raw['payment_method'] ?? null),
            reservationData: Value::string($raw['reservation_data'] ?? null),
            ipaddressV4: Value::string($raw['ipaddress_v4'] ?? null),
            schemeid: Value::string($raw['schemeid'] ?? null),
            acqTranId: Value::string($raw['acq_tran_id'] ?? null),
            version3ds: Value::integer($raw['version_3ds'] ?? null),
            isTest: Value::boolean($raw['is_test'] ?? null),
            maskedIban: Value::string($raw['masked_iban'] ?? null),
            payerIban: Value::string($raw['payer_iban'] ?? null),
            payerName: Value::string($raw['payer_name'] ?? null),
            raw: $raw,
        );
    }
}

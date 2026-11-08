<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

/**
 * Details about the payer that Flitt passes to the bank (reservation_data). Flitt accepts Latin letters, digits and
 * separators in these values. Fiscal product lines are not covered.
 */
final readonly class ReservationData
{
    public function __construct(
        public ?string $phonemobile = null,
        public ?string $customerAddress = null,
        public ?string $customerCountry = null,
        public ?string $customerState = null,
        public ?string $customerName = null,
        public ?string $customerCity = null,
        public ?string $customerZip = null,
        public ?string $account = null,
        public ?string $uuid = null,
        public ?string $settlementId = null,
    ) {}

    /**
     * @internal Flitt's form: base64 of compact JSON, without the fields that are not set.
     */
    public function toParameter(): string
    {
        $fields = array_filter([
            'phonemobile' => $this->phonemobile,
            'customer_address' => $this->customerAddress,
            'customer_country' => $this->customerCountry,
            'customer_state' => $this->customerState,
            'customer_name' => $this->customerName,
            'customer_city' => $this->customerCity,
            'customer_zip' => $this->customerZip,
            'account' => $this->account,
            'uuid' => $this->uuid,
            'settlement_id' => $this->settlementId,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        return base64_encode(json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}

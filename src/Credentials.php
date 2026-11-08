<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt;

use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use JsonSerializable;
use LogicException;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * One Flitt merchant's id and secret keys.
 *
 * The keys never appear in dumps, JSON, logs or stack traces, and the object cannot be serialized, so they stay out of
 * queue payloads and caches too: queue the merchant id and resolve the credentials where they are used.
 */
final readonly class Credentials implements JsonSerializable
{
    private const string MASK = '********';

    private SensitiveParameterValue $paymentKey;

    private ?SensitiveParameterValue $creditKey;

    /**
     * @throws InvalidParameterException for a merchant id below 1 or an empty key
     */
    public function __construct(
        public int $merchantId,
        #[SensitiveParameter]
        string $paymentKey,
        #[SensitiveParameter]
        ?string $creditKey = null,
    ) {
        if ($merchantId < 1) {
            throw InvalidParameterException::invalid('merchantId', 'must be a positive number');
        }

        if ($paymentKey === '') {
            throw InvalidParameterException::invalid('paymentKey', 'must not be empty');
        }

        if ($creditKey === '') {
            throw InvalidParameterException::invalid('creditKey', 'must not be empty; leave it out when there is none');
        }

        $this->paymentKey = new SensitiveParameterValue($paymentKey);
        $this->creditKey = $creditKey === null ? null : new SensitiveParameterValue($creditKey);
    }

    /**
     * @internal
     */
    public function paymentKey(): string
    {
        /** @var string $key */
        $key = $this->paymentKey->getValue();

        return $key;
    }

    /**
     * @internal
     */
    public function creditKey(): ?string
    {
        /** @var string|null $key */
        $key = $this->creditKey?->getValue();

        return $key;
    }

    /**
     * @return array{merchantId: int, paymentKey: string, creditKey: string|null}
     */
    public function __debugInfo(): array
    {
        return $this->masked();
    }

    /**
     * @return array{merchantId: int, paymentKey: string, creditKey: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->masked();
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException(
            'Flitt credentials are not serialized, so their keys stay out of queues and caches. '
            .'Pass the merchant id instead and resolve the credentials where they are used.',
        );
    }

    /**
     * @return array{merchantId: int, paymentKey: string, creditKey: string|null}
     */
    private function masked(): array
    {
        return [
            'merchantId' => $this->merchantId,
            'paymentKey' => self::MASK,
            'creditKey' => $this->creditKey === null ? null : self::MASK,
        ];
    }
}

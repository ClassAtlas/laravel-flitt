<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Builders;

use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\ReservationData;

/**
 * @internal The options and checks that every order-creating builder shares. Each option sets the Flitt parameter of
 * the same name and returns a new builder.
 */
trait BuildsOrder
{
    /**
     * @var array<string, string|int|bool>
     */
    private array $options = [];

    /**
     * Where Flitt posts the callback for each event on the order.
     */
    public function serverCallbackUrl(string $url): self
    {
        return $this->with('server_callback_url', $url);
    }

    /**
     * Seconds until the order expires; Flitt's default is 36000 (10 hours).
     */
    public function lifetime(int $seconds): self
    {
        if ($seconds < 1) {
            throw InvalidParameterException::invalid('lifetime', 'must be at least 1 second');
        }

        return $this->with('lifetime', $seconds);
    }

    /**
     * Echoed back in every answer and callback.
     */
    public function merchantData(string $merchantData): self
    {
        return $this->with('merchant_data', $merchantData);
    }

    /**
     * Hold the money, to capture it later.
     */
    public function preauth(bool $preauth = true): self
    {
        return $this->with('preauth', $preauth);
    }

    public function senderEmail(string $email): self
    {
        return $this->with('sender_email', $email);
    }

    public function productId(string $productId): self
    {
        return $this->with('product_id', $productId);
    }

    /**
     * Payer details Flitt passes to the bank.
     */
    public function reservationData(ReservationData $reservationData): self
    {
        return $this->with('reservation_data', $reservationData->toParameter());
    }

    /**
     * A builder dumps as the parameters it will send, with a card token masked, instead of the whole client behind it.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $parameters = $this->parameters();

        if (isset($parameters['rectoken'])) {
            $parameters['rectoken'] = '********';
        }

        return $parameters;
    }

    /**
     * @throws InvalidParameterException for an empty order id or description, or a negative amount
     */
    private static function checkOrder(string $orderId, int $amount, string $orderDesc): void
    {
        if ($orderId === '') {
            throw InvalidParameterException::invalid('orderId', 'must not be empty');
        }

        if ($orderDesc === '') {
            throw InvalidParameterException::invalid('orderDesc', 'must not be empty');
        }

        if ($amount < 0) {
            throw InvalidParameterException::invalid('amount', 'must not be negative');
        }
    }

    private function with(string $parameter, string|int|bool $value): self
    {
        $builder = clone $this;
        $builder->options[$parameter] = $value;

        return $builder;
    }
}

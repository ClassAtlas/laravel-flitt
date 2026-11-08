<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Builders;

use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Flitt;
use ClassAtlas\Flitt\Requests\RecurringRequest;
use ClassAtlas\Flitt\Responses\Order;
use SensitiveParameter;

/**
 * A charge to a saved card, created with Flitt::recurring(). Every method sets the Flitt parameter of the same name and
 * returns a new builder, so keep the result. Finish with charge().
 *
 * There is no 3-D Secure and no payment page: the answer is final. The card's CVV is never sent.
 */
final class Recurring
{
    use BuildsOrder;

    /**
     * @internal Use Flitt::recurring().
     *
     * @throws InvalidParameterException for an empty order id, description or card token, or a negative amount
     */
    public function __construct(
        private readonly Flitt $client,
        private readonly string $orderId,
        private readonly int $amount,
        private readonly Currency $currency,
        private readonly string $orderDesc,
        #[SensitiveParameter]
        private readonly string $rectoken,
    ) {
        self::checkOrder($orderId, $amount, $orderDesc);

        if ($rectoken === '') {
            throw InvalidParameterException::invalid('rectoken', 'must not be empty');
        }
    }

    /**
     * The payer's IP address, when the charge happens while they are present.
     */
    public function clientIp(string $clientIp): self
    {
        return $this->with('client_ip', $clientIp);
    }

    /**
     * Charges the saved card. The answer is final: there is no 3-D Secure and no page. After ConnectionFailedException,
     * read the order's status before charging again.
     */
    public function charge(): Order
    {
        $request = new RecurringRequest($this->parameters());

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * @internal The parameters under Flitt's names, as they will be sent.
     *
     * @return array<string, string|int|bool>
     */
    public function parameters(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_desc' => $this->orderDesc,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'rectoken' => $this->rectoken,
            ...$this->options,
        ];
    }
}

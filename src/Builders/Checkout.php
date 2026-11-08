<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Builders;

use ClassAtlas\Flitt\Enums\Currency;
use ClassAtlas\Flitt\Enums\Lang;
use ClassAtlas\Flitt\Enums\PaymentMethod;
use ClassAtlas\Flitt\Enums\PaymentSystem;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Flitt;
use ClassAtlas\Flitt\Requests\CheckoutTokenRequest;
use ClassAtlas\Flitt\Requests\CheckoutUrlRequest;
use ClassAtlas\Flitt\Responses\CheckoutToken;
use ClassAtlas\Flitt\Responses\CheckoutUrl;

/**
 * A hosted checkout, created with Flitt::checkout(). Every method sets the Flitt parameter of the same name and returns
 * a new builder, so keep the result. Finish with url() or token().
 */
final class Checkout
{
    use BuildsOrder;

    /**
     * @internal Use Flitt::checkout().
     *
     * @throws InvalidParameterException for an empty order id or description, or a negative amount
     */
    public function __construct(
        private readonly Flitt $client,
        private readonly string $orderId,
        private readonly int $amount,
        private readonly Currency $currency,
        private readonly string $orderDesc,
    ) {
        self::checkOrder($orderId, $amount, $orderDesc);
    }

    /**
     * Where the payer's browser returns after paying.
     */
    public function responseUrl(string $url): self
    {
        return $this->with('response_url', $url);
    }

    /**
     * Where the payer lands after "Cancel".
     */
    public function cancelUrl(string $url): self
    {
        return $this->with('cancel_url', $url);
    }

    /**
     * Flitt's default is on: a declined payer may try again on the same page until the order expires.
     */
    public function delayed(bool $delayed): self
    {
        return $this->with('delayed', $delayed);
    }

    /**
     * Ask for a card token, to charge the card again later with a recurring charge.
     */
    public function requiredRectoken(bool $requiredRectoken = true): self
    {
        return $this->with('required_rectoken', $requiredRectoken);
    }

    /**
     * Approve the card only to keep its token; Flitt says it reverses the amount itself.
     */
    public function verification(bool $verification = true): self
    {
        return $this->with('verification', $verification);
    }

    /**
     * The payment page's language.
     */
    public function lang(Lang $lang): self
    {
        return $this->with('lang', $lang->value);
    }

    /**
     * A checkout design from Flitt's merchant portal.
     */
    public function designId(string $designId): self
    {
        return $this->with('design_id', $designId);
    }

    /**
     * The payment systems the page offers, such as open banking or installments. Neither can be reversed through
     * the API, and their callbacks carry the payer's IBAN and name.
     */
    public function paymentSystems(PaymentSystem ...$paymentSystems): self
    {
        if ($paymentSystems === []) {
            throw InvalidParameterException::invalid('paymentSystems', 'needs at least one payment system');
        }

        $values = array_map(static fn (PaymentSystem $system): string => $system->value, $paymentSystems);

        return $this->with('payment_systems', implode(',', array_unique($values)));
    }

    /**
     * The bank to preselect for open banking or installments.
     */
    public function paymentMethod(PaymentMethod $paymentMethod): self
    {
        return $this->with('payment_method', $paymentMethod->value);
    }

    /**
     * Creates the order and returns the URL of Flitt's payment page.
     */
    public function url(): CheckoutUrl
    {
        $request = new CheckoutUrlRequest($this->parameters());

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * Creates the order and returns a token for Flitt's embedded checkout or mobile SDKs.
     */
    public function token(): CheckoutToken
    {
        $request = new CheckoutTokenRequest($this->parameters());

        return $request->createDtoFromResponse($this->client->send($request));
    }

    /**
     * @internal The parameters under Flitt's names, as they will be sent.
     *
     * @return array<string, string|int|bool|null>
     */
    public function parameters(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_desc' => $this->orderDesc,
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            ...$this->options,
        ];
    }
}

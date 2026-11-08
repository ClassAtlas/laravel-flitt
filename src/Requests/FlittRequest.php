<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Requests;

use BackedEnum;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Protocol\AnswerReader;
use ClassAtlas\Flitt\Signing\SigningKey;
use LogicException;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

/**
 * @internal What every Flitt call shares. The concrete requests are final and named in the README, so an app can fake
 * them with Saloon's MockClient; their constructors and methods stay internal.
 *
 * A request declares Flitt's parameters only. FlittConnector adds merchant_id, version and the signature when it is
 * sent, and reads the answer back through AnswerReader.
 */
abstract class FlittRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /**
     * The call's parameters by Flitt's own names: a closed set, because Flitt rejects a signature over a parameter it
     * does not know. Booleans are sent as Y and N; "" and null are not sent.
     *
     * @return array<string, string|int|bool|BackedEnum|null>
     */
    abstract public function parameters(): array;

    /**
     * The operation's name in logs and error messages, such as "status".
     */
    abstract public function operation(): string;

    public function signingKey(): SigningKey
    {
        return SigningKey::PAYMENT;
    }

    /**
     * Whether Flitt signs a successful answer to this call. Checkout URL and token, capture and the transaction list
     * come back unsigned; everything else must carry a valid signature.
     */
    public function answerIsSigned(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function answerFields(Response $response): array
    {
        return AnswerReader::fields($response, self::credentialsOf($response), $this->operation(), $this->answerIsSigned());
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function answerRows(Response $response): array
    {
        return AnswerReader::rows($response, $this->operation());
    }

    private static function credentialsOf(Response $response): Credentials
    {
        $connector = $response->getConnector();

        if (! $connector instanceof FlittConnector) {
            throw new LogicException('Flitt requests are sent through '.FlittConnector::class.'.');
        }

        return $connector->credentials();
    }
}

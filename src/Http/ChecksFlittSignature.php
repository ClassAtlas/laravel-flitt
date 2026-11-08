<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Http;

use ClassAtlas\Flitt\Callbacks\CallbackVerifier;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\UnknownMerchantException;
use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Protocol\Envelope;
use ClassAtlas\Flitt\Protocol\Value;
use ClassAtlas\Flitt\Responses\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use LogicException;

/**
 * @internal What CallbackRequest and BrowserReturnRequest share: the body is checked while Laravel resolves the request,
 * before the controller runs. A bad or missing signature or an unknown merchant answers 403, a body that cannot be
 * read answers 400, and either is logged as a warning with the merchant id and order id only.
 *
 * @phpstan-require-extends FormRequest
 */
trait ChecksFlittSignature
{
    private ?Order $order = null;

    /**
     * The order Flitt sent, checked.
     */
    public function order(): Order
    {
        return $this->order ?? throw new LogicException('The request has not been checked yet.');
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function validateResolved(): void
    {
        $payload = $this->flittPayload();

        try {
            $order = $payload === null ? null : $this->container->make(CallbackVerifier::class)->check($payload, 'the '.self::FLITT_SOURCE);
        } catch (InvalidSignatureException|UnknownMerchantException $exception) {
            $this->rejectFlittBody(403, $exception instanceof UnknownMerchantException ? 'unknown merchant' : 'signature', $payload);
        }

        $this->order = $order ?? $this->rejectFlittBody(400, 'unreadable', $payload);
    }

    /**
     * The decoded body. A raw body is read as Flitt sent it; when Laravel has already parsed a form (as tests do), its
     * fields are used, which are the same strings.
     *
     * @return array<array-key, mixed>|null
     */
    private function flittPayload(): ?array
    {
        $raw = $this->getContent();

        if ($raw !== '') {
            return Body::parse($raw, $this->headers->get('Content-Type'));
        }

        $fields = $this->request->all();

        return $fields === [] ? null : $fields;
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     */
    private function rejectFlittBody(int $status, string $reason, ?array $payload): never
    {
        if ($payload !== null && Envelope::is($payload)) {
            $payload = Envelope::order($payload);
        }

        $channel = $this->container->make('config')->get('flitt.log_channel');

        $this->container->make('log')->channel(is_string($channel) && $channel !== '' ? $channel : null)->warning('Flitt '.self::FLITT_SOURCE.' rejected', [
            'reason' => $reason,
            'merchant_id' => Value::integer($payload['merchant_id'] ?? null),
            'order_id' => Value::string($payload['order_id'] ?? null),
        ]);

        throw new HttpResponseException(new Response('', $status));
    }
}

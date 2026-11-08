<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\ApiErrorException;
use ClassAtlas\Flitt\Exceptions\ConnectionFailedException;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Tests\Support\ProbeRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

function answer(MockResponse $mock, ?Credentials $credentials = null): Response
{
    return new FlittConnector($credentials ?? new Credentials(1549901, 'test', 'testcredit'))
        ->send(new ProbeRequest, new MockClient([ProbeRequest::class => $mock]));
}

/**
 * @param  array<string, mixed>  $fields
 * @return array<string, mixed>
 */
function signedWith(string $key, array $fields): array
{
    return [...$fields, 'signature' => Signer::sign($fields, $key)];
}

/**
 * @param  array<string, mixed>  $order
 * @return array<string, mixed>
 */
function envelopeSignedWith(string $key, array $order, array $beside = []): array
{
    $data = base64_encode(json_encode(['order' => $order], JSON_THROW_ON_ERROR));

    return ['data' => $data, 'version' => '2.0', 'signature' => Signer::signData($data, $key), ...$beside];
}

$order = [
    'order_id' => 'inv-1042',
    'merchant_id' => 1549901,
    'order_status' => 'approved',
    'tran_type' => 'purchase',
    'amount' => '12500',
    'merchant_data' => '',
    'response_status' => 'success',
];

it('reads a signed order and checks its signature', function () use ($order) {
    $fields = new ProbeRequest()->fields(answer(MockResponse::make(['response' => signedWith('test', $order)])));

    expect($fields)->toMatchArray($order);
});

it('reads a form-encoded answer the same way', function () use ($order) {
    $body = http_build_query(signedWith('test', [...$order, 'order_desc' => 'Tuition, November']));

    $fields = new ProbeRequest()->fields(answer(MockResponse::make($body, 200, [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
    ])));

    expect($fields)->toMatchArray(['order_id' => 'inv-1042', 'order_desc' => 'Tuition, November']);
});

it('refuses an answer that should be signed and is not', function () use ($order) {
    new ProbeRequest()->fields(answer(MockResponse::make(['response' => $order])));
})->throws(InvalidSignatureException::class, "Flitt's answer to the probe call carries no signature");

it('refuses an answer whose signature does not match', function () use ($order) {
    $forged = [...signedWith('test', $order), 'amount' => '1'];

    new ProbeRequest()->fields(answer(MockResponse::make(['response' => $forged])));
})->throws(InvalidSignatureException::class, 'does not match');

it('accepts an unsigned answer from an operation Flitt does not sign', function () {
    $request = new ProbeRequest(signed: false);
    $answer = ['checkout_url' => 'https://pay.flitt.com/merchants/x/default/index.html?token=y', 'response_status' => 'success'];

    $response = new FlittConnector(new Credentials(1549901, 'test'))
        ->send($request, new MockClient([ProbeRequest::class => MockResponse::make(['response' => $answer])]));

    expect($request->fields($response))->toBe($answer);
});

it('still checks a signature that an unsigned operation\'s answer carries', function () use ($order) {
    $request = new ProbeRequest(signed: false);
    $forged = [...signedWith('test', $order), 'amount' => '1'];

    $request->fields(new FlittConnector(new Credentials(1549901, 'test'))
        ->send($request, new MockClient([ProbeRequest::class => MockResponse::make(['response' => $forged])])));
})->throws(InvalidSignatureException::class);

it('turns every error_code into ApiErrorException', function (array $error, int $code, string $message, ?string $requestId) {
    try {
        new ProbeRequest()->fields(answer(MockResponse::make(['response' => $error])));
    } catch (ApiErrorException $exception) {
        expect($exception)
            ->errorCode->toBe($code)
            ->errorMessage->toBe($message)
            ->requestId->toBe($requestId)
            ->operation->toBe('probe')
            ->getCode()->toBe($code)
            ->getMessage()->toBe("Flitt refused the probe call with error {$code}: {$message}");

        return;
    }

    $this->fail('No ApiErrorException.');
})->with([
    'the usual error' => [
        ['error_code' => 1018, 'error_message' => 'Order not found', 'request_id' => 'MvXurVYUAmzjk', 'response_status' => 'failure'],
        1018, 'Order not found', 'MvXurVYUAmzjk',
    ],
    'without response_status, as 2.0 and list errors come' => [
        ['error_code' => 1014, 'error_message' => 'Invalid signature', 'request_id' => 'gH0T7GxdD4GrO'],
        1014, 'Invalid signature', 'gH0T7GxdD4GrO',
    ],
    'with response_status success, as subscription errors come' => [
        ['response_status' => 'success', 'error_message' => 'Order not found', 'error_code' => 1018, 'request_id' => 'x1'],
        1018, 'Order not found', 'x1',
    ],
    'an error code as text, and no request id' => [
        ['error_code' => '1002', 'error_message' => 'Application error'],
        1002, 'Application error', null,
    ],
]);

it('treats any HTTP status but 200 as a transport failure', function (int $status) {
    new ProbeRequest()->fields(answer(MockResponse::make('<html>No Such Resource</html>', $status, ['Content-Type' => 'text/html'])));
})->with([302, 404, 405, 500, 502])->throws(ConnectionFailedException::class, 'answered the probe call with HTTP');

it('treats a body it cannot read as a failure to answer', function (mixed $body) {
    new ProbeRequest()->fields(answer(MockResponse::make($body, 200, ['Content-Type' => 'text/html'])));
})->with([
    'an HTML page' => ['<html>Maintenance</html>'],
    'broken JSON' => ['{"response": {'],
    'a response root that is not an object' => ['{"response": "ok"}'],
    'an empty object' => ['{"response": {}}'],
    'a list where an order was expected' => ['{"response": [{"id": 1}]}'],
])->throws(ConnectionFailedException::class, 'could not be read');

it('reads a protocol 2.0 envelope, checks it, and keeps the status beside the data', function () use ($order) {
    $fields = new ProbeRequest()->fields(answer(MockResponse::make([
        'response' => envelopeSignedWith('test', $order, ['response_status' => 'success']),
    ])));

    expect($fields)->toMatchArray([...$order, 'response_status' => 'success']);
});

it('refuses a protocol 2.0 envelope with a forged signature or unreadable data', function (array $envelope) {
    new ProbeRequest()->fields(answer(MockResponse::make(['response' => $envelope])));
})->with([
    'forged' => fn () => [...envelopeSignedWith('test', ['order_id' => 'inv-1042']), 'signature' => sha1('forged')],
    'signed with the wrong key' => fn () => envelopeSignedWith('another-key', ['order_id' => 'inv-1042']),
    'unsigned' => fn () => array_diff_key(envelopeSignedWith('test', ['order_id' => 'inv-1042']), ['signature' => true]),
])->throws(InvalidSignatureException::class);

it('treats an envelope whose data cannot be decoded as unreadable', function () {
    new ProbeRequest()->fields(answer(MockResponse::make([
        'response' => ['data' => '%%%not base64%%%', 'version' => '2.0', 'signature' => sha1('x')],
    ])));
})->throws(ConnectionFailedException::class, 'could not be read');

it('checks a payout order with the credit key, whichever call fetched it', function () use ($order) {
    $payout = [...$order, 'tran_type' => 'p2p credit'];

    expect(new ProbeRequest()->fields(answer(MockResponse::make(['response' => signedWith('testcredit', $payout)]))))
        ->toMatchArray($payout)
        ->and(new ProbeRequest()->fields(answer(MockResponse::make([
            'response' => envelopeSignedWith('testcredit', $payout),
        ]))))->toMatchArray($payout);
});

it('refuses a payout order signed with the payment key', function () use ($order) {
    new ProbeRequest()->fields(answer(MockResponse::make([
        'response' => signedWith('test', [...$order, 'tran_type' => 'p2p credit']),
    ])));
})->throws(InvalidSignatureException::class);

it('needs the credit key to check a payout order', function () use ($order) {
    new ProbeRequest()->fields(answer(
        MockResponse::make(['response' => signedWith('testcredit', [...$order, 'tran_type' => 'p2p credit'])]),
        new Credentials(1549901, 'test'),
    ));
})->throws(MissingCredentialsException::class, 'A payout order (tran_type "p2p credit") is signed with the credit key');

it('reads a list answer row by row', function () {
    $rows = [
        ['id' => 2298080697, 'tran_type' => 'purchase', 'amount' => 10.0, 'preauth' => true],
        ['id' => 2298080906, 'tran_type' => 'reverse', 'amount' => 2.0, 'reverse_id' => 'lf-rev-1'],
    ];

    $asFlittWritesIt = '{"response": [{"id": 2298080697, "tran_type": "purchase", "amount": 10.0, "preauth": true}, '
        .'{"id": 2298080906, "tran_type": "reverse", "amount": 2.0, "reverse_id": "lf-rev-1"}]}';

    expect(new ProbeRequest()->rows(answer(MockResponse::make($asFlittWritesIt, 200, ['Content-Type' => 'application/json']))))
        ->toBe($rows)
        ->and(new ProbeRequest()->rows(answer(MockResponse::make(['response' => []]))))->toBe([]);
});

it('reads a list answer\'s error, and refuses anything else in its place', function () {
    expect(fn () => new ProbeRequest()->rows(answer(MockResponse::make([
        'response' => ['error_code' => 1016, 'error_message' => 'Merchant not found', 'request_id' => 'r1'],
    ]))))->toThrow(ApiErrorException::class, 'error 1016')
        ->and(fn () => new ProbeRequest()->rows(answer(MockResponse::make([
            'response' => ['order_id' => 'inv-1042'],
        ]))))->toThrow(ConnectionFailedException::class, 'could not be read')
        ->and(fn () => new ProbeRequest()->rows(answer(MockResponse::make([
            'response' => ['not a row'],
        ]))))->toThrow(ConnectionFailedException::class, 'could not be read');
});

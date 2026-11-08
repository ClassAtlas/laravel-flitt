<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Protocol\Envelope;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\Verifier;
use ClassAtlas\Flitt\Tests\Support\FixtureGuard;
use ClassAtlas\Flitt\Tests\Support\Live;
use ClassAtlas\Flitt\Tests\Support\ProbeCalls;
use ClassAtlas\Flitt\Tests\Support\ProbeRequest;
use ClassAtlas\Flitt\Tests\Support\Samples;
use ClassAtlas\Flitt\Tests\Support\Scrubber;
use Saloon\Exceptions\FixtureMissingException;
use Saloon\Exceptions\StrayRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

dataset('fixture files', function (): array {
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/Fixtures', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'json') {
            $files[substr($file->getPathname(), strlen(__DIR__) + 1)] = [$file->getPathname()];
        }
    }

    return $files;
});

it('finds nothing in any committed fixture that must not be there', function (string $path) {
    expect(FixtureGuard::problems((string) file_get_contents($path)))->toBe([]);
})->with('fixture files');

it('catches a planted merchant, IP, card or email wherever it hides', function (string $plant, string $problem) {
    $sample = Samples::first(static fn (array $s): bool => $s['encoding'] === 'json' && $s['root'] === 'flat');
    $order = json_decode($sample['body'], true, flags: JSON_THROW_ON_ERROR);

    $planted = match ($plant) {
        'a foreign merchant id' => [...$order, 'merchant_id' => 1396424],
        'a foreign merchant in a form body' => ['body' => http_build_query([...$order, 'merchant_id' => '1396424'])],
        'a foreign merchant in a 2.0 envelope' => [
            'data' => base64_encode(json_encode(['order' => [...$order, 'merchant_id' => 1396424]], JSON_THROW_ON_ERROR)),
            'version' => '2.0',
        ],
        'a real payer IP in additional_info' => [...$order, 'additional_info' => '{"ipaddress_v4": "91.151.136.10"}'],
        'a real card' => [...$order, 'masked_card' => '535528XXXXXX4401'],
        'a real email' => [...$order, 'sender_email' => 'nino@gmail.com'],
        'a real payer IBAN' => [...$order, 'additional_info' => '{"payer_iban": "GE29TB7894545082100008"}'],
        'a real payer name' => [...$order, 'additional_info' => '{"payer_name": "Nino Beridze"}'],
    };

    $problems = FixtureGuard::problems(json_encode(['samples' => [$planted]], JSON_THROW_ON_ERROR));

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toContain($problem);
})->with([
    ['a foreign merchant id', 'merchant 1396424'],
    ['a foreign merchant in a form body', 'merchant 1396424'],
    ['a foreign merchant in a 2.0 envelope', 'merchant 1396424'],
    ['a real payer IP in additional_info', 'IP 91.151.136.10'],
    ['a real card', '535528XXXXXX4401'],
    ['a real email', 'email address'],
    ['a real payer IBAN', 'demo bank'],
    ['a real payer name', 'not a test name'],
]);

it('refuses to save an answer it cannot scrub', function () {
    Scrubber::answer('checkout_url=https%3A%2F%2Fpay.flitt.com&response_status=success');
})->throws(JsonException::class);

it('refuses to save JSON that is not a Flitt answer', function () {
    Scrubber::answer('{"unexpected": true}');
})->throws(RuntimeException::class, 'cannot read');

it('scrubs a recorded answer and signs it again so it still verifies', function () {
    $recorded = [
        'order_id' => 'lf-live-approved-261108-ab12',
        'merchant_id' => 1549901,
        'order_status' => 'approved',
        'tran_type' => 'purchase',
        'sender_email' => 'nino@gmail.com',
        'additional_info' => json_encode([
            'ipaddress_v4' => '91.151.136.10',
            'payer_iban' => 'GE29TB7894545082100008',
            'masked_iban' => 'GE29TB************0008',
            'payer_name' => 'Nino Beridze',
            'settlement_fee' => 0.0,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        'response_signature_string' => 'test|…',
    ];
    $recorded['signature'] = Signer::sign(array_diff_key($recorded, ['response_signature_string' => true]), 'test');

    $scrubbed = json_decode(
        Scrubber::answer(json_encode(['response' => $recorded], JSON_THROW_ON_ERROR), ['lf-live-approved-261108-ab12' => 'lf-fixture-approved']),
        true,
        flags: JSON_THROW_ON_ERROR,
    )['response'];

    Verifier::flat($scrubbed, Live::credentials(), 'the scrubbed answer');

    expect($scrubbed)
        ->order_id->toBe('lf-fixture-approved')
        ->sender_email->toBe('nino@example.com')
        ->additional_info->toBe('{"ipaddress_v4": "203.0.113.10", "payer_iban": "GE00XB0000000000000000", "masked_iban": "GE00XB************0000", "payer_name": "Test Payer", "settlement_fee": 0.0}')
        ->response_signature_string->toStartWith('**********|')
        ->and(FixtureGuard::problems(json_encode($scrubbed, JSON_THROW_ON_ERROR)))->toBe([]);
});

it('signs a scrubbed payout again with the credit key, and a 2.0 envelope over its new data', function () {
    $payout = ['order_id' => 'p-1', 'merchant_id' => 1549901, 'tran_type' => 'p2p credit', 'additional_info' => '{"ipaddress_v4": "91.151.136.10"}'];
    $payout['signature'] = Signer::sign($payout, 'testcredit');

    $flat = json_decode(Scrubber::answer(json_encode(['response' => $payout], JSON_THROW_ON_ERROR)), true, flags: JSON_THROW_ON_ERROR)['response'];
    $envelope = json_decode(Scrubber::answer(json_encode(['response' => ProbeCalls::envelopeSignedWith('testcredit', $payout)], JSON_THROW_ON_ERROR)), true, flags: JSON_THROW_ON_ERROR)['response'];
    $order = Envelope::order($envelope);

    Verifier::flat($flat, Live::credentials(), 'the scrubbed payout');
    Verifier::envelope($envelope, $envelope['data'], $order, Live::credentials(), 'the scrubbed envelope');

    expect($order['additional_info'])->toContain('203.0.113.10');
});

it('fails when a fixture is missing, instead of calling Flitt', function () {
    Live::answer([ProbeRequest::class => 'missing/never-recorded']);

    new FlittConnector(Live::credentials())->send(new ProbeRequest);
})->throws(FixtureMissingException::class)->skip(fn (): bool => Live::reachesFlitt(), 'Fixtures only.');

it('refuses to send anything but a mock in the default run', function () {
    new FlittConnector(Live::credentials())->send(new ProbeRequest);
})->throws(StrayRequestException::class)->skip(fn (): bool => Live::reachesFlitt(), 'Fixtures only.');

it('refuses any merchant but the test merchant when a run reaches Flitt', function () {
    $before = getenv('FLITT_LIVE');
    putenv('FLITT_LIVE=1');
    Live::tearDown();
    Live::setUp();

    try {
        new FlittConnector(new Credentials(1396424, 'not-a-test-key'))
            ->send(new ProbeRequest, new MockClient([ProbeRequest::class => MockResponse::make(['response' => []])]));
    } finally {
        putenv($before === false ? 'FLITT_LIVE' : "FLITT_LIVE={$before}");
        Live::tearDown();
        Live::setUp();
    }
})->throws(LogicException::class, 'only call Flitt\'s public test merchant 1549901');

it('gives every run its own order ids, and fixtures a fixed one', function () {
    expect(Live::orderId('approved'))->toBe(Live::reachesFlitt() ? Live::orderId('approved') : 'lf-fixture-approved');
});

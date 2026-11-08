<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Exceptions\InvalidSignatureException;
use ClassAtlas\Flitt\Protocol\Envelope;
use ClassAtlas\Flitt\Signing\RequestBody;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\SigningKey;
use ClassAtlas\Flitt\Signing\Verifier;
use ClassAtlas\Flitt\Tests\Support\TestCredentials;
use ClassAtlas\Flitt\Tests\Support\Vectors;

it('signs every request Flitt accepted exactly as Flitt expected', function (array $vector) {
    $params = array_diff_key($vector['params'], array_flip($vector['not_signed'] ?? []));

    expect(Signer::signatureString($params, $vector['key']))
        ->toBe(str_replace('<KEY>', $vector['key'], $vector['presign']))
        ->and(Signer::sign($params, $vector['key']))->toBe($vector['signature']);
})->with(Vectors::dataset('request_accepted'));

it('builds the body Flitt accepted with version 1.0.1 sent and signed', function () {
    $vector = Vectors::dataset('request_accepted')['checkout/url version 1.0.1 included in signature'][0];

    $body = RequestBody::signed(
        array_diff_key($vector['params'], ['merchant_id' => true, 'version' => true]),
        TestCredentials::testMerchant(),
        SigningKey::PAYMENT,
        'vector',
    );

    expect($body)->toEqual([...$vector['params'], 'signature' => $vector['signature']]);
});

it('never produces a signature Flitt refused', function (array $vector) {
    $params = $vector['params'];
    $sent = $vector['signature_sent'];

    match ($vector['name']) {
        'sigsem empty string included as empty slot',
        'sigsem string 0 excluded',
        'checkout/url version 1.0.1 excluded from signature' => expect(Signer::sign($params, 'test'))->not->toBe($sent),
        'sigsem uppercase hex signature' => expect(Signer::sign($params, 'test'))
            ->toBe(strtolower($sent))
            ->not->toBe($sent),
        'p2pcredit signed with payment key (wrong)' => expect(Signer::sign($params, 'testcredit'))->not->toBe($sent)
            ->and(Signer::sign($params, 'test'))->toBe($sent),
        'status of purchase order signed with testcredit' => expect(Signer::sign($params, 'test'))->not->toBe($sent)
            ->and(Signer::sign($params, 'testcredit'))->toBe($sent),
        // Flitt signs only the parameters it knows, so every request is a closed set and never carries one like this.
        'sigsem unknown param included in signature' => expect(Signer::sign($params, 'test'))->toBe($sent)
            ->and(Signer::sign(array_diff_key($params, ['zz_unknown' => true]), 'test'))->not->toBe($sent),
        'edge pipe in merchant_data' => expect(fn () => RequestBody::signed(
            array_diff_key($params, ['merchant_id' => true]),
            TestCredentials::testMerchant(),
            SigningKey::PAYMENT,
            'vector',
        ))->toThrow(InvalidParameterException::class, 'merchant_data'),
    };
})->with(Vectors::dataset('request_rejected'));

it('accepts every answer Flitt signed', function (array $vector) {
    Verifier::flat($vector['response'], TestCredentials::testMerchant(), 'the vector');

    expect(Signer::sign(
        array_diff_key($vector['response'], ['signature' => true, 'response_signature_string' => true]),
        $vector['key'],
    ))->toBe($vector['signature']);
})->with(Vectors::dataset('response'));

it('refuses a signed answer once any value changes', function (array $vector) {
    $response = $vector['response'];
    $response['order_id'] .= '-forged';

    Verifier::flat($response, TestCredentials::testMerchant(), 'the vector');
})->with(Vectors::dataset('response'))->throws(InvalidSignatureException::class, 'does not match');

it('refuses a signed answer checked with the other key', function (array $vector) {
    $swapped = $vector['key'] === 'test'
        ? new Credentials(1549901, 'testcredit', 'test')
        : new Credentials(1549901, 'testcredit', 'test-other');

    Verifier::flat($vector['response'], $swapped, 'the vector');
})->with(Vectors::dataset('response'))->throws(InvalidSignatureException::class);

it('refuses a signed answer whose signature is in uppercase', function () {
    $response = Vectors::all()['response'][0]['response'];
    $response['signature'] = strtoupper($response['signature']);

    Verifier::flat($response, TestCredentials::testMerchant(), 'the vector');
})->throws(InvalidSignatureException::class, 'does not match');

it('signs protocol 2.0 data as the base64 string', function (array $vector) {
    expect(Signer::signData($vector['request']['data'], $vector['key']))->toBe($vector['request']['signature']);
})->with(Vectors::dataset('protocol_2_0'));

it('checks a protocol 2.0 answer Flitt signed, and reads the order inside', function () {
    $response = Vectors::dataset('protocol_2_0')['checkout/url protocol 2.0 envelope'][0]['response'];
    $order = Envelope::order($response);

    expect(Envelope::is($response))->toBeTrue()
        ->and($order)->toHaveKeys(['checkout_url', 'payment_id']);

    Verifier::envelope($response, $response['data'], $order, TestCredentials::testMerchant(), 'the vector');
    Verifier::envelope([...$response, 'signature' => strtoupper($response['signature'])], $response['data'], $order, TestCredentials::testMerchant(), 'the vector');
})->throws(InvalidSignatureException::class, 'does not match');

<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Exceptions\InvalidParameterException;
use ClassAtlas\Flitt\Exceptions\MissingCredentialsException;
use ClassAtlas\Flitt\Signing\RequestBody;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Signing\SigningKey;
use ClassAtlas\Flitt\Tests\Support\ProbeLang;

it('sends booleans as Y and N, enums as their value, and leaves out "" and null', function () {
    $body = RequestBody::signed([
        'order_id' => 'inv-1042',
        'amount' => 12_500,
        'preauth' => true,
        'delayed' => false,
        'lang' => ProbeLang::KA,
        'merchant_data' => '',
        'sender_email' => null,
        'product_id' => '0',
        'lifetime' => 0,
    ], new Credentials(1549901, 'test'), SigningKey::PAYMENT, 'probe');

    expect(array_diff_key($body, ['signature' => true]))->toBe([
        'order_id' => 'inv-1042',
        'amount' => 12_500,
        'preauth' => 'Y',
        'delayed' => 'N',
        'lang' => 'ka',
        'product_id' => '0',
        'lifetime' => 0,
        'merchant_id' => 1549901,
        'version' => '1.0.1',
    ])->and($body['signature'])->toBe(Signer::sign(array_diff_key($body, ['signature' => true]), 'test'));
});

it('signs payouts with the credit key and everything else with the payment key', function () {
    $credentials = new Credentials(1549901, 'test', 'testcredit');
    $parameters = ['order_id' => 'payout-88'];

    $payment = RequestBody::signed($parameters, $credentials, SigningKey::PAYMENT, 'probe');
    $credit = RequestBody::signed($parameters, $credentials, SigningKey::CREDIT, 'probe');

    expect($payment['signature'])->toBe(Signer::sign(array_diff_key($payment, ['signature' => true]), 'test'))
        ->and($credit['signature'])->toBe(Signer::sign(array_diff_key($credit, ['signature' => true]), 'testcredit'));
});

it('refuses a payout when the credentials have no credit key', function () {
    RequestBody::signed(['order_id' => 'payout-88'], new Credentials(1549901, 'test'), SigningKey::CREDIT, 'card payout');
})->throws(MissingCredentialsException::class, 'The card payout call is signed with the credit key');

it('refuses a "|" in any value before anything is sent, naming the parameter but not the value', function () {
    try {
        RequestBody::signed(
            ['order_desc' => 'Tuition | secret-value'],
            new Credentials(1549901, 'test'),
            SigningKey::PAYMENT,
            'probe',
        );
    } catch (InvalidParameterException $exception) {
        expect($exception->getMessage())->toContain('order_desc')->not->toContain('secret-value');

        return;
    }

    $this->fail('The "|" was not refused.');
});

<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Contracts\CredentialsResolver;
use ClassAtlas\Flitt\Credentials;
use ClassAtlas\Flitt\Http\BrowserReturnRequest;
use ClassAtlas\Flitt\Http\CallbackRequest;
use ClassAtlas\Flitt\Protocol\Body;
use ClassAtlas\Flitt\Signing\Signer;
use ClassAtlas\Flitt\Tests\Support\Samples;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['flitt.merchant_id' => '1549901', 'flitt.payment_key' => 'test', 'flitt.credit_key' => 'testcredit']);

    $answer = static fn ($request): array => [
        'order_id' => $request->order()->orderId,
        'order_status' => $request->order()->orderStatus?->value,
        'merchant_id' => $request->order()->merchantId,
    ];

    Route::post('flitt/callback', static fn (CallbackRequest $request): array => $answer($request));
    Route::post('flitt/return', static fn (BrowserReturnRequest $request): array => $answer($request));
});

it('accepts every recorded callback and return through both requests, raw as Flitt sends them', function (array $sample) {
    $fields = Samples::fields($sample);
    $expected = ['order_id' => $fields['order_id'], 'order_status' => $sample['order_status'], 'merchant_id' => 1549901];

    Samples::post($this, $sample, '/flitt/callback')->assertOk()->assertExactJson($expected);
    Samples::post($this, $sample, '/flitt/return')->assertOk()->assertExactJson($expected);
})->with(Samples::dataset());

it('accepts a browser return that Laravel has already parsed as a form, as in an app\'s own tests', function (array $sample) {
    $this->post('/flitt/return', Body::parse($sample['body'], $sample['content_type']))
        ->assertOk()
        ->assertJson(['order_status' => $sample['order_status']]);
})->with(array_filter(Samples::dataset(), static fn (array $set): bool => $set[0]['encoding'] === 'form'));

it('checks a payout callback with the credit key', function () {
    $sample = Samples::first(static fn (array $s): bool => $s['signed_with'] === 'testcredit');

    Samples::post($this, $sample, '/flitt/callback')->assertOk();

    config(['flitt.credit_key' => null]);
    Samples::post($this, $sample, '/flitt/callback')->assertServerError();
});

it('reads both returns open banking sends for one payment', function () {
    $returns = array_values(array_filter(
        array_column(Samples::dataset(), 0),
        static fn (array $s): bool => $s['channel'] === 'response_url' && str_contains($s['body'], 'payment_system=opb'),
    ));
    $orderIds = array_map(static fn (array $s): string => Samples::fields($s)['order_id'], $returns);

    expect(count($orderIds))->toBeGreaterThan(count(array_unique($orderIds)));

    foreach ($returns as $return) {
        Samples::post($this, $return, '/flitt/return')->assertOk();
    }
});

it('answers 403 to a forged or unsigned body, and logs only the merchant and order ids', function (string $forgery) {
    Log::spy();
    $sample = Samples::first(static fn (array $s): bool => $s['encoding'] === 'json' && $s['root'] === 'flat');
    $order = json_decode($sample['body'], true, flags: JSON_THROW_ON_ERROR);

    $forged = match ($forgery) {
        'a changed amount' => [...$order, 'amount' => '1'],
        'no signature' => array_diff_key($order, ['signature' => true]),
        'an uppercase signature' => [...$order, 'signature' => strtoupper($order['signature'])],
        'signed with another key' => [...$order, 'signature' => Signer::sign(array_diff_key($order, ['signature' => true, 'response_signature_string' => true]), 'stolen-key')],
    };

    Samples::post($this, $sample, '/flitt/callback', json_encode($forged, JSON_THROW_ON_ERROR))->assertForbidden()->assertContent('');

    Log::shouldHaveReceived('warning')->once()->withArgs(static fn (string $message, array $context): bool => $message === 'Flitt callback rejected'
        && $context === ['reason' => 'signature', 'merchant_id' => 1549901, 'order_id' => $order['order_id']]);
})->with(['a changed amount', 'no signature', 'an uppercase signature', 'signed with another key']);

it('answers 403 to a merchant the resolver does not know', function () {
    $sample = Samples::first(static fn (array $s): bool => $s['encoding'] === 'json' && $s['root'] === 'flat');
    $order = json_decode($sample['body'], true, flags: JSON_THROW_ON_ERROR);
    $foreign = [...$order, 'merchant_id' => 1396424];
    $foreign['signature'] = Signer::sign(array_diff_key($foreign, ['signature' => true, 'response_signature_string' => true]), 'test');

    Samples::post($this, $sample, '/flitt/callback', json_encode($foreign, JSON_THROW_ON_ERROR))->assertForbidden();
});

it('uses the app\'s own resolver for each merchant', function () {
    $sample = Samples::first(static fn (array $s): bool => $s['encoding'] === 'json' && $s['root'] === 'flat');
    config(['flitt.merchant_id' => null, 'flitt.payment_key' => null]);

    app()->instance(CredentialsResolver::class, new class implements CredentialsResolver
    {
        public function forMerchant(int $merchantId): ?Credentials
        {
            return $merchantId === 1549901 ? new Credentials(1549901, 'test') : null;
        }
    });

    Samples::post($this, $sample, '/flitt/callback')->assertOk();
});

it('answers 400 to a body it cannot read', function (string $contentType, string $body) {
    $this->call('POST', '/flitt/callback', server: ['CONTENT_TYPE' => $contentType], content: $body)
        ->assertStatus(400)
        ->assertContent('');
})->with([
    'nothing' => ['application/json', ''],
    'broken JSON' => ['application/json', '{"order_id": '],
    'a JSON list' => ['application/json', '[1, 2]'],
    'an HTML page' => ['text/html', '<html lang="en">Hi</html>'],
    'no merchant id' => ['application/json', '{"order_id": "inv-1042", "signature": "x"}'],
    'an envelope that is not base64' => ['application/json', '{"data": "%%%", "version": "2.0", "signature": "x"}'],
]);

it('never answers 200 without a checked order', function () {
    $this->call('POST', '/flitt/return', server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'order_id=inv-1042&merchant_id=1549901')
        ->assertForbidden();
});

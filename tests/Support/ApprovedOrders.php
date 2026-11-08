<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Tests\Support;

use ClassAtlas\Flitt\FlittConnector;
use ClassAtlas\Flitt\Responses\Order;

/**
 * Test only: an approved order on the test merchant, made with Step1Request. The answer for the default run comes from
 * the fixture named after the order.
 */
final class ApprovedOrders
{
    public static function create(string $name, int $amount, bool $preauth = false, bool $requiredRectoken = false): Order
    {
        Live::answer([Step1Request::class => "step1/{$name}"]);

        $request = new Step1Request(Live::orderId($name), $amount, $preauth, $requiredRectoken);

        return $request->createDtoFromResponse(new FlittConnector(Live::credentials(), timeout: 30)->send($request));
    }
}

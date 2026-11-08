<?php

declare(strict_types=1);

return [

    /*
     * The merchant the package uses when a call is made without credentials of its own,
     * and the only merchant whose callbacks the default resolver accepts.
     */
    'merchant_id' => env('FLITT_MERCHANT_ID'),

    // Signs payments, and checks their answers and callbacks.
    'payment_key' => env('FLITT_PAYMENT_KEY'),

    // Signs payouts, and checks payout answers and callbacks. Only needed for payouts.
    'credit_key' => env('FLITT_CREDIT_KEY'),

    // Seconds to wait for Flitt's answer, and for the connection.
    'timeout' => (int) env('FLITT_TIMEOUT', 10),
    'connect_timeout' => (int) env('FLITT_CONNECT_TIMEOUT', 3),

    // One line per call, never with bodies, keys, signatures, card data, emails or IP addresses.
    'log_requests' => (bool) env('FLITT_LOG_REQUESTS', false),
    'log_channel' => env('FLITT_LOG_CHANNEL'),

];

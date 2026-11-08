<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A callback Flitt posts to server_callback_url, checked before your controller runs; read it with order().
 *
 * The callback is the source of truth. Exclude its route from CSRF, and answer 200 once you have stored it: Flitt
 * repeats any other answer for about a day.
 */
final class CallbackRequest extends FormRequest
{
    use ChecksFlittSignature;

    private const string FLITT_SOURCE = 'callback';
}

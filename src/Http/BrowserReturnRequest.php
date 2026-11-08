<?php

declare(strict_types=1);

namespace ClassAtlas\Flitt\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The payer's browser coming back to response_url or cancel_url with the order, checked before your controller runs;
 * read it with order().
 *
 * It arrives as a cross-site form POST, so exclude its route from CSRF and don't rely on the session. Show the result
 * with a 303 redirect; the callback, not this return, decides whether the order is paid.
 */
final class BrowserReturnRequest extends FormRequest
{
    use ChecksFlittSignature;

    private const string FLITT_SOURCE = 'browser return';
}

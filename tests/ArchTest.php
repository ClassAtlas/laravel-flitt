<?php

declare(strict_types=1);

use ClassAtlas\Flitt\Exceptions\FlittException;
use ClassAtlas\Flitt\Requests\FlittRequest;

arch('every file declares strict types')
    ->expect('ClassAtlas\Flitt')
    ->toUseStrictTypes();

arch('HTTP goes through the Saloon connector only')
    ->expect('ClassAtlas\Flitt')
    ->not->toUse([
        'Illuminate\Support\Facades\Http',
        'Illuminate\Http\Client',
        'GuzzleHttp',
        'curl_init',
        'curl_exec',
        'curl_multi_exec',
        'file_get_contents',
        'fsockopen',
    ])
    ->ignoring('ClassAtlas\Flitt\FlittConnector');

arch('no debugging calls')
    ->expect(['dd', 'ddd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r', 'debug_zval_refcount'])
    ->not->toBeUsedIn('ClassAtlas\Flitt');

arch('classes are final, apart from the two bases')
    ->expect('ClassAtlas\Flitt')
    ->classes()
    ->toBeFinal()
    ->ignoring([FlittException::class, FlittRequest::class]);

arch('the bases are abstract')
    ->expect([FlittException::class, FlittRequest::class])
    ->toBeAbstract();

arch('response objects are readonly')
    ->expect('ClassAtlas\Flitt\Responses')
    ->classes()
    ->toBeReadonly();

arch('enums are backed by strings')
    ->expect('ClassAtlas\Flitt\Enums')
    ->toBeStringBackedEnums();

arch('every exception extends FlittException')
    ->expect('ClassAtlas\\Flitt\\Exceptions')
    ->toExtend(FlittException::class)
    ->ignoring(FlittException::class);

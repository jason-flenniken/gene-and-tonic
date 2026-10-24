<?php

use Illuminate\Support\Facades\Http;

it('fails a raw Http request as a stray request', function () {
    Http::get('https://example.com');
})->throws(RuntimeException::class, 'Attempted request to [https://example.com]');

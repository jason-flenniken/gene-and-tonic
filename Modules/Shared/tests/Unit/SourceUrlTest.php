<?php

use Modules\Shared\Support\SourceUrl;

it('keeps a plain https url', function () {
    expect(SourceUrl::sanitize('https://pubmed.ncbi.nlm.nih.gov/1/'))->toBe('https://pubmed.ncbi.nlm.nih.gov/1/')
        ->and(SourceUrl::sanitize('http://example.com/a?b=c'))->toBe('http://example.com/a?b=c');
});

it('drops unsafe urls', function (string $url) {
    expect(SourceUrl::sanitize($url))->toBeNull();
})->with([
    'userinfo' => 'https://user:pw@x.com',
    'username only' => 'https://user@x.com/',
    'non-ascii host' => 'https://exämple.com',
    'javascript scheme' => 'javascript:alert(1)',
    'ftp scheme' => 'ftp://x.com/file',
    'not a url' => 'not a url',
    'empty' => '',
    'too long' => 'https://example.com/'.str_repeat('a', 2030),
]);

it('accepts a url of exactly 2048 characters', function () {
    $url = 'https://example.com/'.str_repeat('a', 2048 - 20);

    expect(strlen($url))->toBe(2048)
        ->and(SourceUrl::sanitize($url))->toBe($url);
});

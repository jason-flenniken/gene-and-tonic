<?php

beforeEach(function () {
    $this->withoutVite();
    config(['app.name' => 'Gene & Tonic']);
});

it('shows the app name once, escaped, in the page title', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match('#<title>(.*?)</title>#s', $html, $matches);

    expect($matches[1] ?? '')
        ->toContain('Gene &amp; Tonic')
        ->not->toContain('&amp;amp;');
});

it('links the brand favicons and apple touch icon', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('href="/favicon.ico"', false)
        ->assertSee('href="/favicon.svg"', false)
        ->assertSee('href="/apple-touch-icon.png"', false);
});

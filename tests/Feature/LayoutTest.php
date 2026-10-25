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

it('binds the dark variant to a class so the OS colour scheme cannot switch Flux into dark styling', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('@custom-variant dark (&:where(.dark, .dark *));')
        ->and(file_get_contents(resource_path('views/layouts/app.blade.php')))->not->toContain('@fluxAppearance');
});

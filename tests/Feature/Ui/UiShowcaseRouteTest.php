<?php

beforeEach(fn () => $this->withoutVite());

it('does not register /_ui outside local', function () {
    $this->get('/_ui')->assertNotFound();
});

it('lists every component on /_ui in local', function () {
    $previous = $_SERVER['APP_ENV'] ?? null;
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'local';
    putenv('APP_ENV=local');

    try {
        $this->refreshApplication();
        $this->withoutVite();

        expect(app()->isLocal())->toBeTrue();

        $response = $this->get('/_ui')->assertOk();

        foreach ([
            'x-brand', 'x-icon', 'x-ui.panel', 'x-ui.stat', 'x-ui.icon-tile', 'x-ui.link-row', 'x-ui.button',
            'x-ui.segmented', 'x-ui.chip', 'x-ui.status', 'x-ui.progress-steps', 'x-ui.grade',
            'x-ui.allele-pair', 'x-ui.notice', 'x-ui.dropzone',
        ] as $name) {
            $response->assertSee($name);
        }
    } finally {
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $previous ?? 'testing';
        putenv('APP_ENV='.($previous ?? 'testing'));
    }
});

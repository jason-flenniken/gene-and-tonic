<?php

use Modules\Reports\Services\TemplateRegistry;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Enums\ResearchStatus;

function renderDownloadMenu(?ResearchStatus $status, int $supplementId = 7): string
{
    return (string) test()->view('reports::components.download-menu', [
        'supplementId' => $supplementId,
        'status' => $status,
    ]);
}

it('is registered in the supplement header slot', function () {
    expect(app(PageActions::class)->for('supplement.header'))
        ->toContain('reports::components.download-menu');
});

it('renders a template select with every configured label and the default preselected', function () {
    $html = renderDownloadMenu(ResearchStatus::Complete);

    foreach (app(TemplateRegistry::class)->all() as $key => $template) {
        expect($html)
            ->toContain('value="'.$key.'"')
            ->toContain($template['label']);
    }

    expect($html)
        ->toMatch('/<option\s[^>]*selected="selected"[^>]*value="'.app(TemplateRegistry::class)->default().'"/')
        ->toContain('x-model="template"');
});

it('links the download to the route for the default template', function () {
    $default = app(TemplateRegistry::class)->default();
    $html = renderDownloadMenu(ResearchStatus::Complete, 7);

    expect($html)
        ->toContain('href="'.route('reports.supplements.pdf', ['supplement' => 7]).'?template='.$default.'"')
        ->toContain('Download PDF')
        ->not->toContain(' target=');
});

it('rewrites the link template from the select in the browser', function () {
    $html = renderDownloadMenu(ResearchStatus::Complete, 7);

    expect($html)
        ->toContain('encodeURIComponent(template)')
        ->toContain('x-bind:href="pdfUrl')
        ->toContain("pdfUrl: '".str_replace('/', '\\/', route('reports.supplements.pdf', ['supplement' => 7]))."'");
});

it('renders nothing unless research is complete', function (?ResearchStatus $status) {
    expect(trim(renderDownloadMenu($status)))->toBe('');
})->with([
    'queued' => [ResearchStatus::Queued],
    'searching' => [ResearchStatus::Searching],
    'failed' => [ResearchStatus::Failed],
    'no research' => [null],
]);

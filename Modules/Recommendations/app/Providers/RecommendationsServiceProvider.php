<?php

namespace Modules\Recommendations\Providers;

use Livewire\Livewire;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Services\GeneMatchVariantLinks;
use Modules\Recommendations\Services\RuleBasedGeneticRelevanceReader;
use Modules\Shared\Contracts\PageActions;
use Modules\Shared\Contracts\VariantLinks;
use Nwidart\Modules\Support\ModuleServiceProvider;

class RecommendationsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Recommendations';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'recommendations';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Components resolve as <livewire:recommendations::name /> and Route::livewire('...', 'recommendations::name').
        Livewire::addNamespace(
            namespace: $this->nameLower,
            viewPath: module_path($this->name, 'resources/views/livewire'),
            classNamespace: 'Modules\\'.$this->name.'\\Livewire',
            classPath: module_path($this->name, 'app/Livewire'),
            classViewPath: module_path($this->name, 'resources/views/livewire'),
        );

        app(PageActions::class)->register('supplement.panels', 'recommendations::slots.genetic-panel');
        app(PageActions::class)->register('supplement.preview', 'recommendations::slots.preview-strip');
        app(PageActions::class)->register('supplement.card', 'recommendations::slots.match-count');
    }

    public function register(): void
    {
        parent::register();

        // Bind this module's public contracts (app/Contracts) to their private implementations here.

        $this->app->bind(GeneticRelevanceReader::class, RuleBasedGeneticRelevanceReader::class);
        $this->app->bind(VariantLinks::class, GeneMatchVariantLinks::class);
    }
}

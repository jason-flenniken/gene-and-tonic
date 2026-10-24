<?php

namespace Modules\Supplements\Providers;

use Livewire\Livewire;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Repositories\EloquentResearchRepository;
use Modules\Supplements\Repositories\EloquentSupplementRepository;
use Modules\Supplements\Repositories\EloquentSupplementResearchReader;
use Modules\Supplements\Repositories\ResearchRepository;
use Modules\Supplements\Repositories\SupplementRepository;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SupplementsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Supplements';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'supplements';

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

        // Components resolve as <livewire:supplements::name /> and Route::livewire('...', 'supplements::name').
        Livewire::addNamespace(
            namespace: $this->nameLower,
            viewPath: module_path($this->name, 'resources/views/livewire'),
            classNamespace: 'Modules\\'.$this->name.'\\Livewire',
            classPath: module_path($this->name, 'app/Livewire'),
            classViewPath: module_path($this->name, 'resources/views/livewire'),
        );

        $this->app->make(ProfileRegistry::class)->register('supplements.research', config('supplements.research_profile'));
    }

    public function register(): void
    {
        parent::register();

        // Bind this module's public contracts (app/Contracts) to their private implementations here.

        $this->app->bind(SupplementRepository::class, EloquentSupplementRepository::class);
        $this->app->bind(ResearchRepository::class, EloquentResearchRepository::class);
        $this->app->bind(SupplementResearchReader::class, EloquentSupplementResearchReader::class);
    }
}

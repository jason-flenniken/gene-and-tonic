<?php

namespace Modules\Genetics\Providers;

use Livewire\Livewire;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Repositories\EloquentGeneticProfileReader;
use Modules\Genetics\Support\Extraction\AiMarkerExtractor;
use Modules\Genetics\Support\Extraction\LocalTextExtractor;
use Modules\Genetics\Support\Extraction\MarkerExtractor;
use Modules\Genetics\Support\Extraction\TextExtractor;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GeneticsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Genetics';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'genetics';

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

        // Components resolve as <livewire:genetics::name /> and Route::livewire('...', 'genetics::name').
        Livewire::addNamespace(
            namespace: $this->nameLower,
            viewPath: module_path($this->name, 'resources/views/livewire'),
            classNamespace: 'Modules\\'.$this->name.'\\Livewire',
            classPath: module_path($this->name, 'app/Livewire'),
            classViewPath: module_path($this->name, 'resources/views/livewire'),
        );

        $registry = $this->app->make(ProfileRegistry::class);
        $registry->register('genetics.extract', config('genetics.extract_profile'));
        $registry->register('genetics.lookup', config('genetics.lookup_profile'));
    }

    public function register(): void
    {
        parent::register();

        // Bind this module's public contracts (app/Contracts) to their private implementations here.

        $this->app->bind(GeneticProfileReader::class, EloquentGeneticProfileReader::class);
        $this->app->bind(TextExtractor::class, LocalTextExtractor::class);
        $this->app->bind(MarkerExtractor::class, AiMarkerExtractor::class);
    }
}

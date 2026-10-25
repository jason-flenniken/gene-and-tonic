<?php

namespace Modules\Genetics\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\Genetics\Console\DemoFilesCommand;
use Modules\Genetics\Console\GroupVariantsCommand;
use Modules\Genetics\Console\ReapStaleCommand;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
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

        $this->flushProfileCacheOnWrites();

        $registry = $this->app->make(ProfileRegistry::class);
        $registry->register('genetics.extract', config('genetics.extract_profile'));
        $registry->register('genetics.lookup', config('genetics.lookup_profile'));
        $registry->register('genetics.group', config('genetics.group_profile'));

        $this->commands([ReapStaleCommand::class, DemoFilesCommand::class, GroupVariantsCommand::class]);

        $this->app->booted(function (): void {
            $this->app->make(Schedule::class)
                ->command('genetics:reap-stale')
                ->everyFiveMinutes();
        });
    }

    public function register(): void
    {
        parent::register();

        // Bind this module's public contracts (app/Contracts) to their private implementations here.

        $this->app->scoped(EloquentGeneticProfileReader::class);
        $this->app->scoped(GeneticProfileReader::class, fn (): EloquentGeneticProfileReader => $this->app->make(EloquentGeneticProfileReader::class));
        $this->app->bind(TextExtractor::class, LocalTextExtractor::class);
        $this->app->bind(MarkerExtractor::class, AiMarkerExtractor::class);
    }

    /**
     * Keep the request-scoped profile cache honest: any saved or deleted variant row invalidates it.
     */
    private function flushProfileCacheOnWrites(): void
    {
        $flush = function (): void {
            $this->app->make(EloquentGeneticProfileReader::class)->flush();
        };

        foreach ([UserVariant::class, GeneVariant::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }
}

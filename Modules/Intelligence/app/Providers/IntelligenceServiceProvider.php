<?php

namespace Modules\Intelligence\Providers;

use Livewire\Livewire;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Repositories\AiRequestRepository;
use Modules\Intelligence\Repositories\EloquentAiRequestRepository;
use Modules\Intelligence\Services\ConfigProfileRegistry;
use Modules\Intelligence\Services\LaravelAiTextGenerator;
use Nwidart\Modules\Support\ModuleServiceProvider;

class IntelligenceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Intelligence';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'intelligence';

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

        // Components resolve as <livewire:intelligence::name /> and Route::livewire('...', 'intelligence::name').
        Livewire::addNamespace(
            namespace: $this->nameLower,
            viewPath: module_path($this->name, 'resources/views/livewire'),
            classNamespace: 'Modules\\'.$this->name.'\\Livewire',
            classPath: module_path($this->name, 'app/Livewire'),
            classViewPath: module_path($this->name, 'resources/views/livewire'),
        );
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ProfileRegistry::class, ConfigProfileRegistry::class);
        $this->app->bind(TextGenerator::class, LaravelAiTextGenerator::class);
        $this->app->bind(AiRequestRepository::class, EloquentAiRequestRepository::class);
    }
}

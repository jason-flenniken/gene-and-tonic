<?php

namespace Modules\Shared\Providers;

use Livewire\Livewire;
use Modules\Shared\Contracts\PageActions;
use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Services\InMemoryPageActions;
use Modules\Shared\Services\NoVariantLinks;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Shared';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'shared';

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

        // Components resolve as <livewire:shared::name /> and Route::livewire('...', 'shared::name').
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

        // Bind this module's public contracts (app/Contracts) to their private implementations here.

        $this->app->singleton(PageActions::class, InMemoryPageActions::class);

        // An empty default that Recommendations rebinds; bindIf keeps it from
        // overwriting that binding whichever provider registers first.
        $this->app->bindIf(VariantLinks::class, NoVariantLinks::class);
    }
}

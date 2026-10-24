<?php

namespace Modules\Shared\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Shared';

    /**
     * Define the routes for the module.
     */
    public function map(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }
}

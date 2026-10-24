<?php

use Illuminate\Support\Facades\Route;
use Modules\Reports\Http\Controllers\SupplementReportController;

Route::get('/reports/supplements/{supplement}.pdf', SupplementReportController::class)
    ->whereNumber('supplement')
    ->middleware('throttle:reports')
    ->name('reports.supplements.pdf');

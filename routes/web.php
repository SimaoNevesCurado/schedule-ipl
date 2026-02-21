<?php

declare(strict_types=1);

use App\Http\Controllers\ScheduleExplorerController;
use App\Http\Controllers\TurnSelectionExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TurnSelectionExportController::class, 'index'])->name('home');
Route::get('/turns/export', [TurnSelectionExportController::class, 'index'])->name('turn-selection-export.index');
Route::post('/turns/export/apply', [TurnSelectionExportController::class, 'apply'])->name('turn-selection-export.apply');

Route::get('/schedules', [ScheduleExplorerController::class, 'index'])->name('schedules.index');

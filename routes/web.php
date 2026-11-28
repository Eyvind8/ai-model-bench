<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\DatasetItemController;
use App\Http\Controllers\LabelingController;
use App\Http\Controllers\ModelController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\ScenarioController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::controller(ModelController::class)->prefix('models')->name('models.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('sync', 'sync')->name('sync');
    Route::post('{model}/toggle', 'toggle')->name('toggle');
    Route::post('{model}/ping', 'ping')->name('ping');
});

Route::controller(ScenarioController::class)->prefix('scenarios')->name('scenarios.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('sync', 'sync')->name('sync');
    Route::get('{scenario}', 'show')->name('show');
});

Route::controller(DatasetController::class)->prefix('datasets')->name('datasets.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('{dataset}', 'show')->name('show');
    Route::delete('{dataset}', 'destroy')->name('destroy');
    Route::post('{dataset}/urls', 'importUrls')->name('urls');
    Route::post('{dataset}/import', 'importFile')->name('import');
    Route::post('{dataset}/refetch', 'refetch')->name('refetch');
});

Route::prefix('datasets/{dataset}')->name('datasets.')->scopeBindings()->group(function () {
    Route::post('items', [DatasetItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/edit', [DatasetItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [DatasetItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [DatasetItemController::class, 'destroy'])->name('items.destroy');
    Route::get('label', [LabelingController::class, 'show'])->name('label');
    Route::post('items/{item}/label', [LabelingController::class, 'store'])->name('items.label');
});

Route::get('playground', [RunController::class, 'playground'])->name('playground');

Route::controller(RunController::class)->prefix('runs')->name('runs.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('forecast', 'forecast')->name('forecast');
    Route::post('/', 'store')->name('store');
    Route::get('{run}', 'show')->name('show');
    Route::get('{run}/progress', 'progress')->name('progress');
    Route::post('{run}/cancel', 'cancel')->name('cancel');
    Route::get('{run}/export', 'export')->name('export');
});

Route::controller(ResultController::class)->prefix('results')->name('results.')->group(function () {
    Route::get('{result}', 'show')->name('show');
    Route::post('{result}/rate', 'rate')->name('rate');
});

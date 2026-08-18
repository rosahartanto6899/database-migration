<?php

use App\Http\Controllers\MigrationToolController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('migration')->name('migration.')->group(function () {
    Route::get('/', [MigrationToolController::class, 'index'])->name('index');
    Route::post('/test-connection', [MigrationToolController::class, 'testConnection'])->name('test-connection');
    Route::post('/create-database', [MigrationToolController::class, 'createDatabase'])->name('create-database');
    Route::post('/create-schema', [MigrationToolController::class, 'createSchema'])->name('create-schema');
    Route::post('/target-tables', [MigrationToolController::class, 'targetTables'])->name('target-tables');
    Route::post('/truncate', [MigrationToolController::class, 'truncate'])->name('truncate');
    Route::post('/migrate', [MigrationToolController::class, 'migrate'])->name('migrate');
    Route::post('/migrate-chunk', [MigrationToolController::class, 'migrateChunk'])->name('migrate-chunk');
});

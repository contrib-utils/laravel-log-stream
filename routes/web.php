<?php

use Illuminate\Support\Facades\Route;
use LogScope\Http\Controllers\Api\EntriesController;
use LogScope\Http\Controllers\Api\FilesController;
use LogScope\Http\Controllers\Api\SearchController;
use LogScope\Http\Controllers\Api\SourcesController;
use LogScope\Http\Controllers\AppController;
use LogScope\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| LogScope routes
|--------------------------------------------------------------------------
|
| Mounted under the configured prefix with the configured middleware. The
| login routes sit OUTSIDE the access gate (so an unauthenticated user can
| reach the form); everything else is gated by EnsureLogScopeAccess, which
| is appended to the group middleware by the service provider.
|
*/

// --- Login (ungated) ---------------------------------------------------------
Route::get('login', [AuthController::class, 'showLogin'])->name('logscope.login');
Route::post('login', [AuthController::class, 'login'])->name('logscope.login.attempt');
Route::post('logout', [AuthController::class, 'logout'])->name('logscope.logout');

// --- JSON API (gated) --------------------------------------------------------
Route::prefix('api')->name('logscope.api.')->middleware('logscope.access')->group(function () {
    Route::get('ping', fn () => response()->json(['data' => ['ok' => true]]))->name('ping');

    Route::get('sources', [SourcesController::class, 'index'])->name('sources');
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('files', [FilesController::class, 'index'])->name('files');
    Route::get('files/{fileId}/entries', [EntriesController::class, 'index'])->name('files.entries');
    Route::get('entries/{entryId}', [EntriesController::class, 'show'])->name('entries.show');

    // File operations — additionally gated by the allow_file_operations switch.
    Route::middleware('logscope.file-ops')->group(function () {
        Route::get('files/{fileId}/download', [FilesController::class, 'download'])->name('files.download');
        Route::post('files/{fileId}/clear', [FilesController::class, 'clear'])->name('files.clear');
        Route::delete('files/{fileId}', [FilesController::class, 'destroy'])->name('files.destroy');
    });
});

// --- SPA shell (gated, catch-all so client-side routing / deep links work) ---
Route::middleware('logscope.access')
    ->get('{any?}', AppController::class)
    ->where('any', '^(?!api|login|logout).*$')
    ->name('logscope.app');

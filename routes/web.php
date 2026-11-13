<?php

use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TimeEntryController::class, 'index'])->name('entries.index');
Route::post('/jira/fetch', [TimeEntryController::class, 'fetchJira'])->name('jira.fetch');
Route::get('/export', [TimeEntryController::class, 'export'])->name('entries.export');

Route::post('/entries', [TimeEntryController::class, 'store'])->name('entries.store');
Route::get('/entries/{entry}/edit', [TimeEntryController::class, 'edit'])->name('entries.edit');
Route::put('/entries/{entry}', [TimeEntryController::class, 'update'])->name('entries.update');
Route::delete('/entries/{entry}', [TimeEntryController::class, 'destroy'])->name('entries.destroy');
Route::post('/entries/{entry}/start', [TimeEntryController::class, 'start'])->name('entries.start');
Route::post('/entries/{entry}/pause', [TimeEntryController::class, 'pause'])->name('entries.pause');
Route::post('/entries/{entry}/filled', [TimeEntryController::class, 'toggleFilled'])->name('entries.filled');

// Google Form auto-fill
Route::post('/entries/{entry}/fill-form', [TimeEntryController::class, 'fillForm'])->name('entries.fillForm');
Route::post('/fill-form/day', [TimeEntryController::class, 'fillFormDay'])->name('entries.fillFormDay');

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MemoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'create'])->name('login');
Route::get('/login', fn () => redirect()->route('login'));
Route::post('/login', [AuthController::class, 'store'])->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/memories', [MemoryController::class, 'index'])->name('memories.index');
    Route::get('/memories/bubbles', [MemoryController::class, 'bubbles'])->name('memories.bubbles');
    Route::get('/memories/create-preview', [MemoryController::class, 'createPreview'])->name('memories.create.preview');
    Route::get('/memories/create', [MemoryController::class, 'create'])->name('memories.create');
    Route::post('/memories', [MemoryController::class, 'store'])->name('memories.store');
    Route::get('/memories/{memory}/edit', [MemoryController::class, 'edit'])->name('memories.edit');
    Route::put('/memories/{memory}', [MemoryController::class, 'update'])->name('memories.update');
    Route::get('/memories/{memory}', [MemoryController::class, 'show'])->name('memories.show');
    Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])->name('memories.destroy');
});

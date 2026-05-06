<?php

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\MemorySpaceController;
use App\Http\Controllers\Api\V1\SecretUnlockController;
use App\Http\Controllers\Api\V1\TagController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', static fn (): JsonResponse => response()->json([
        'service' => 'bunshin-memory-api',
        'status' => 'ok',
        'version' => '0.1.0',
    ]))->name('api.v1.health');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/memories', [MemoryController::class, 'index'])
            ->name('api.v1.memories.index');

        Route::post('/memories', [MemoryController::class, 'store'])
            ->name('api.v1.memories.store');

        Route::get('/memories/{memory}', [MemoryController::class, 'show'])
            ->name('api.v1.memories.show');

        Route::patch('/memories/{memory}', [MemoryController::class, 'update'])
            ->name('api.v1.memories.update');

        Route::delete('/memories/{memory}', [MemoryController::class, 'destroy'])
            ->name('api.v1.memories.destroy');

        Route::get('/memory-space', [MemorySpaceController::class, 'show'])
            ->name('api.v1.memory-space.show');

        Route::post('/secret-unlocks', [SecretUnlockController::class, 'store'])
            ->name('api.v1.secret-unlocks.store');

        Route::apiResource('categories', CategoryController::class)
            ->names('api.v1.categories');

        Route::get('/tags', [TagController::class, 'index'])
            ->name('api.v1.tags.index');
    });
});

<?php

use App\Http\Controllers\UserTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-tasks', [UserTaskController::class, 'index'])->name('user-tasks.index');
    Route::post('/my-tasks/{task}/start', [UserTaskController::class, 'start'])->name('user-tasks.start');
    Route::get('/my-tasks/attempts/{attempt}', [UserTaskController::class, 'show'])->name('user-tasks.show');
    Route::get('/my-tasks/attempts/{attempt}/status', [UserTaskController::class, 'status'])->name('user-tasks.status');
    Route::post('/my-tasks/attempts/{attempt}/restart', [UserTaskController::class, 'restart'])->name('user-tasks.restart');
    Route::post('/my-tasks/attempts/{attempt}/submit', [UserTaskController::class, 'submit'])->name('user-tasks.submit');
});

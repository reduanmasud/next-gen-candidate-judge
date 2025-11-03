<?php

use App\Http\Controllers\UserTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::get('/my-tasks', [UserTaskController::class, 'index'])->name('user-tasks.index');
});
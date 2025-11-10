<?php

use App\Http\Controllers\ServerProvisionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::get('servers', [ServerProvisionController::class, 'index'])->name('servers.index');
    Route::get('servers/create', [ServerProvisionController::class, 'create'])->name('servers.create');
    Route::post('servers', [ServerProvisionController::class, 'store'])->name('servers.store');
    Route::get('servers/{server}', [ServerProvisionController::class, 'show'])->name('servers.show');
    Route::get('servers/{server}/status', [ServerProvisionController::class, 'status'])->name('servers.status');
});


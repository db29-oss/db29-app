<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;

Route::get('/', [PageController::class, 'homepage']);
Route::get('login', [PageController::class, 'login'])->name('login');
Route::post('login', [PageController::class, 'postLogin']);
Route::post('logout', [PageController::class, 'postLogout'])->name('post-logout');
Route::post('register', [PageController::class, 'postRegister'])->name('post-register');

Route::get('faq', [PageController::class, 'faq'])->name('faq');

Route::group(['middleware' => 'auth'], function () {
    Route::get('dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::prefix('instance')->group(function () {
        Route::get('/', [PageController::class, 'instance'])->name('instance');
        Route::delete('/', [PageController::class, 'deleteInstance'])->name('deleteInstance');
        Route::get('register', [PageController::class, 'registerInstance'])->name('register-instance');
        Route::post('register', [PageController::class, 'postRegisterInstance'])
            ->name('post-register-instance');
    });
    Route::get('source', [PageController::class, 'source'])->name('source');
    Route::get('account-update', [PageController::class, 'accountUpdate'])->name('account-update');
    Route::post('account-update', [PageController::class, 'postAccountUpdate'])->name('post-account-update');
    Route::get('advanced-feature', [PageController::class, 'advancedFeature'])->name('advanced-feature');
    Route::post('fill-instance', [PageController::class, 'fillInstance'])->name('fill-instance');
});

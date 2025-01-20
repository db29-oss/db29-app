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
        Route::post('delete', [PageController::class, 'deleteInstance'])->name('delete-instance');
        Route::post('turn-on', [PageController::class, 'turnOnInstance'])->name('turn-on-instance');
        Route::post('turn-off', [PageController::class, 'turnOffInstance'])->name('turn-off-instance');
        Route::get('register', [PageController::class, 'registerInstance'])->name('register-instance');
        Route::post('register', [PageController::class, 'postRegisterInstance'])
            ->name('post-register-instance');
    });
    Route::get('source', [PageController::class, 'source'])->name('source');
    Route::get('prefill', [PageController::class, 'prefill'])->name('prefill');
    Route::post('prefill', [PageController::class, 'postPrefill'])->name('post-prefill');
    Route::get('recharge', [PageController::class, 'recharge'])->name('recharge');
    Route::get('advanced-feature', [PageController::class, 'advancedFeature'])->name('advanced-feature');
    Route::post('fill-instance', [PageController::class, 'fillInstance'])->name('fill-instance');

    Route::prefix('server')->group(function () {
        Route::get('/', [PageController::class, 'server'])->name('server');
        Route::get('add', [PageController::class, 'addServer'])->name('add-server');
        Route::post('add', [PageController::class, 'postAddServer'])->name('post-add-server');
        Route::get('edit', [PageController::class, 'editServer'])->name('edit-server');
        Route::post('edit', [PageController::class, 'postEditServer'])->name('post-edit-server');
        Route::get('delete', [PageController::class, 'deleteServer'])->name('delete-server');
        Route::post('delete', [PageController::class, 'postDeleteServer'])->name('post-delete-server');
    });
});

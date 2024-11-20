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
    Route::get('registered-service', [PageController::class, 'registeredService'])->name('registered-service');
    Route::get('supported-source', [PageController::class, 'supportedSource'])->name('supported-source');
    Route::get('account-update', [PageController::class, 'accountUpdate'])->name('account-update');
    Route::post('account-update', [PageController::class, 'postAccountUpdate'])->name('account-update');
    Route::get('advanced-feature', [PageController::class, 'advancedFeature'])->name('advanced-feature');
});

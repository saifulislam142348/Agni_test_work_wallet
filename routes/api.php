<?php

use App\Http\Controllers\WalletController;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Wallet Routes
    Route::get('/wallet/dashboard', [WalletController::class, 'dashboard']);
    Route::get('/wallet/history', [WalletController::class, 'history']);
    Route::get('/wallet/statement/download', [WalletController::class, 'downloadStatement']);

    // Link Wallet
    Route::post('/wallet/link', [WalletController::class, 'linkWallet']);

    // Add Money
    Route::post('/wallet/add-money', [WalletController::class, 'addMoney']);
});

// Callback Routes (Public)
Route::get('/wallet/link/callback', [WalletController::class, 'linkWalletCallback'])->name('api.bkash.agreement.callback');

Route::get('/wallet/payment/callback', [WalletController::class, 'paymentCallback'])->name('api.bkash.payment.callback');

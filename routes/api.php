<?php

use App\Http\Controllers\WalletController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Wallet Routes
    Route::get('/wallet/dashboard', [WalletController::class, 'dashboard']);
    Route::get('/wallet/history', [WalletController::class, 'history']);
    Route::get('/wallet/statement/download', [WalletController::class, 'downloadStatement']);

    // bKash Agreement flow
    Route::post('/wallet/link', [WalletController::class, 'linkWallet']);
    Route::post('/wallet/link/callback', [WalletController::class, 'linkWalletCallback'])->name('api.bkash.agreement.callback');

    // Add Money
    Route::post('/wallet/add-money', [WalletController::class, 'addMoney']);
    
    // Placeholder callback for add money (if needed by bKash flow difference, but strictly it's immediate in some flows)
    // We used route('api.bkash.payment.callback') in controller
    Route::get('/wallet/payment/callback', function() {
        return response()->json(['message' => 'Payment processed']); 
    })->name('api.bkash.payment.callback');
});

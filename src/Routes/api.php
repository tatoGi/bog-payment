<?php

use Illuminate\Support\Facades\Route;
use Bog\Payment\Controllers\BogCardController;
use Bog\Payment\Controllers\BogPaymentController;

/*
|--------------------------------------------------------------------------
| BOG Payment Routes
|--------------------------------------------------------------------------
|
| Routes are optionally loaded by the service provider.
| You can disable them in the config if you want to define your own routes.
|
*/

if (config('bog-payment.routes.enabled', true)) {

    // BOG Payment Callback - No CSRF protection
    Route::post('/bog/callback', [BogPaymentController::class, 'handleCallback'])
        ->withoutMiddleware(['web', 'verify.csrf.token'])
        ->name('bog.callback');

    // Create BOG order
    Route::post('/bog/orders', [BogPaymentController::class, 'createOrder'])
        ->withoutMiddleware(['verify.csrf.token'])
        ->name('bog.orders.create');

    // Get BOG order details
    Route::get('/bog/orders/{orderId}', [BogPaymentController::class, 'orderDetails'])
        ->name('bog.orders.details');

    // Save card for future payments
    Route::post('/bog/orders/{orderId}/save-card', [BogPaymentController::class, 'saveCard'])
        ->name('bog.orders.save-card');

    // Charge saved card
    Route::post('/bog/orders/{parentOrderId}/charge', [BogPaymentController::class, 'chargeCard'])
        ->name('bog.orders.charge-card');

    // Card management routes (authenticated)
    Route::prefix('bog/cards')->middleware('auth:sanctum')->group(function () {
        Route::post('/add', [BogCardController::class, 'addCard'])->name('bog.cards.add');
        Route::post('/save', [BogCardController::class, 'saveCard'])->name('bog.cards.save');
        Route::get('/', [BogCardController::class, 'listCards'])->name('bog.cards.list');
        Route::delete('/{cardId}', [BogCardController::class, 'deleteCard'])->name('bog.cards.delete');
        Route::post('/{cardId}/set-default', [BogCardController::class, 'setDefaultCard'])->name('bog.cards.set-default');
    });

    // Payment history (authenticated)
    Route::prefix('bog/payments')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [BogPaymentController::class, 'getUserPayments'])->name('bog.payments.list');
    });
}
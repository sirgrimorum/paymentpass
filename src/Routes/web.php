<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => config("sirgrimorum.paymentpass.route_prefix"), 'middleware' => [
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    ], 'as' => "paymentpass::"], function () {
    Route::any('/{service}/{responseType}', '\Sirgrimorum\PaymentPass\PaymentPassHandler@handleResponse')->name('response');
});

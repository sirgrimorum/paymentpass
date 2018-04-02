<?php

Route::group(['prefix' => config("sirgrimorum.paymentpass.route_prefix"), 'middleware' => ['web'], 'as' => "paymentpass::"], function () {
    Route::any('/{service}/{responseType}', '\Sirgrimorum\PaymentPass\PaymentPassHandler@handleResponse')->name('response');
});

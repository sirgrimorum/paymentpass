<?php

Route::group(['prefix' => config("sirgrimorum.paymentpass.route_prefix"), 'as' => "paymentpass::"], function () {
    Route::any('/{service}/{responseType}', '\Sirgrimorum\PaymentPass\PaymentPassHandler@handleResponse')->name('response');
});

<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(array(
        'name' => 'AvtoNarx Backend API',
        'status' => 'ok',
    ));
});

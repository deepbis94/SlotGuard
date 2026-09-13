<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return [
        'name' => 'SlotGuard',
        'health' => '/up',
    ];
});

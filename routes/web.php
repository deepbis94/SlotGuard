<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return [
        'name' => 'SlotGuard',
        'description' => 'Appointment booking API with overlap prevention.',
        'health' => '/up',
        'endpoints' => [
            'GET /api/services',
            'GET /api/services/{id}',
            'POST /api/services/{id}/bookings',
            'GET /api/services/{id}/bookings',
            'DELETE /api/bookings/{id}',
        ],
    ];
});

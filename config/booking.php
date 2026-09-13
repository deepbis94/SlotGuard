<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cancellation window
    |--------------------------------------------------------------------------
    |
    | A confirmed booking cannot be cancelled once its start time is closer
    | than this many hours. Applies to past bookings as well (already started).
    |
    */
    'cancellation_window_hours' => (int) env('BOOKING_CANCELLATION_WINDOW_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Booking timezone
    |--------------------------------------------------------------------------
    |
    | daily_start_time / daily_end_time on services are wall-clock times in
    | this timezone. Incoming ISO-8601 datetimes are converted here before
    | the hours check.
    |
    */
    'timezone' => env('BOOKING_TIMEZONE', 'UTC'),
];

<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * True parallel HTTP requests are not practical in PHPUnit (one PHP process,
 * one in-memory SQLite connection). SQLite also ignores SELECT ... FOR UPDATE.
 *
 * This test simulates the race as two back-to-back requests for the same slot
 * and asserts only one booking is stored. Production concurrency is enforced by:
 *  1. a transaction + lockForUpdate() on the service row (PostgreSQL)
 *  2. the EXCLUDE USING gist constraint (PostgreSQL)
 */
class ConcurrentBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_of_two_identical_booking_attempts_succeeds(): void
    {
        $service = Service::factory()->create([
            'duration_minutes' => 30,
            'daily_start_time' => '09:00:00',
            'daily_end_time' => '17:00:00',
        ]);

        $payload = [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'starts_at' => '2026-09-15T10:00:00Z',
        ];

        $first = $this->postJson("/api/services/{$service->id}/bookings", $payload);
        $second = $this->postJson("/api/services/{$service->id}/bookings", [
            ...$payload,
            'customer_email' => 'grace@example.com',
        ]);

        $first->assertCreated();
        $second->assertConflict();
        $this->assertDatabaseCount('bookings', 1);
    }
}

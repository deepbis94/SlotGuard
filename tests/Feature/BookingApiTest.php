<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_creates_a_confirmed_booking(): void
    {
        $service = $this->service();

        $response = $this->postJson("/api/services/{$service->id}/bookings", [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'starts_at' => '2026-09-15T10:00:00Z',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_name', 'Ada Lovelace')
            ->assertJsonPath('data.customer_email', 'ada@example.com')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.starts_at', '2026-09-15T10:00:00+00:00')
            ->assertJsonPath('data.ends_at', '2026-09-15T10:30:00+00:00');

        $this->assertDatabaseHas('bookings', [
            'service_id' => $service->id,
            'customer_email' => 'ada@example.com',
            'status' => BookingStatus::Confirmed->value,
        ]);
    }

    public function test_overlap_is_rejected_with_conflict(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T10:00:00Z'))
            ->assertCreated();

        $this->postJson("/api/services/{$service->id}/bookings", [
            'customer_name' => 'Grace Hopper',
            'customer_email' => 'grace@example.com',
            'starts_at' => '2026-09-15T10:15:00Z',
        ])
            ->assertConflict()
            ->assertJsonPath('message', 'This time slot overlaps an existing confirmed booking.')
            ->assertJsonPath('errors', []);

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_adjacent_half_open_slots_are_allowed(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T10:00:00Z'))
            ->assertCreated();

        $this->postJson("/api/services/{$service->id}/bookings", [
            'customer_name' => 'Grace Hopper',
            'customer_email' => 'grace@example.com',
            'starts_at' => '2026-09-15T10:30:00Z',
        ])->assertCreated();

        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_cancelled_booking_does_not_block_the_slot(): void
    {
        $service = $this->service();

        Booking::factory()->cancelled()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-16 10:00:00',
            'ends_at' => '2026-09-16 10:30:00',
        ]);

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-16T10:00:00Z'))
            ->assertCreated();
    }

    public function test_past_start_is_rejected(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-13T10:00:00Z'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_start_outside_business_hours_is_rejected(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T08:00:00Z'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_end_after_daily_end_is_rejected(): void
    {
        $service = $this->service(duration: 30, dailyEnd: '17:00:00');

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T16:45:00Z'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_at']);
    }

    public function test_booking_that_ends_exactly_at_daily_end_is_allowed(): void
    {
        $service = $this->service(duration: 30, dailyEnd: '17:00:00');

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T16:30:00Z'))
            ->assertCreated()
            ->assertJsonPath('data.ends_at', '2026-09-15T17:00:00+00:00');
    }

    public function test_cancellation_happy_path(): void
    {
        $service = $this->service();

        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-16 10:00:00',
            'ends_at' => '2026-09-16 10:30:00',
        ]);

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled->value,
        ]);
    }

    public function test_cancellation_inside_24_hour_window_is_rejected(): void
    {
        $service = $this->service();

        $booking = Booking::factory()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-15 07:00:00',
            'ends_at' => '2026-09-15 07:30:00',
        ]);

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertConflict()
            ->assertJsonStructure(['message', 'errors']);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed->value,
        ]);
    }

    public function test_double_cancellation_is_rejected(): void
    {
        $service = $this->service();

        $booking = Booking::factory()->cancelled()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-16 10:00:00',
            'ends_at' => '2026-09-16 10:30:00',
        ]);

        $this->deleteJson("/api/bookings/{$booking->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This booking is already cancelled.');
    }

    public function test_validation_errors_return_field_messages(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", [
            'customer_email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_name', 'customer_email', 'starts_at'])
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_unknown_service_returns_not_found(): void
    {
        $this->postJson('/api/services/999/bookings', $this->payload('2026-09-15T10:00:00Z'))
            ->assertNotFound()
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_lists_services_and_bookings_filtered_by_range(): void
    {
        $service = $this->service();

        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-15T10:00:00Z'))
            ->assertCreated();
        $this->postJson("/api/services/{$service->id}/bookings", $this->payload('2026-09-16T11:00:00Z'))
            ->assertCreated();

        $this->getJson('/api/services')
            ->assertOk()
            ->assertJsonPath('data.0.name', $service->name);

        $this->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 30);

        $this->getJson("/api/services/{$service->id}/bookings?from=2026-09-16T00:00:00Z&to=2026-09-17T00:00:00Z")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-09-16T11:00:00+00:00');
    }

    private function service(int $duration = 30, string $dailyEnd = '17:00:00'): Service
    {
        return Service::factory()->create([
            'duration_minutes' => $duration,
            'daily_start_time' => '09:00:00',
            'daily_end_time' => $dailyEnd,
        ]);
    }

    /**
     * @return array{customer_name: string, customer_email: string, starts_at: string}
     */
    private function payload(string $startsAt): array
    {
        return [
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'ada@example.com',
            'starts_at' => $startsAt,
        ];
    }
}

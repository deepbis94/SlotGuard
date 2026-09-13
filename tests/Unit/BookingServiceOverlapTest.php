<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingServiceOverlapTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_in_memory_sqlite(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    public function test_detects_overlap_only_among_confirmed_bookings(): void
    {
        $service = Service::factory()->create(['duration_minutes' => 30]);

        Booking::factory()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-15 10:00:00',
            'ends_at' => '2026-09-15 10:30:00',
            'status' => BookingStatus::Confirmed,
        ]);

        Booking::factory()->cancelled()->create([
            'service_id' => $service->id,
            'starts_at' => '2026-09-15 11:00:00',
            'ends_at' => '2026-09-15 11:30:00',
        ]);

        $bookings = app(BookingService::class);

        $this->assertTrue($bookings->hasConfirmedOverlap(
            $service->id,
            CarbonImmutable::parse('2026-09-15 10:15:00', 'UTC'),
            CarbonImmutable::parse('2026-09-15 10:45:00', 'UTC'),
        ));

        $this->assertFalse($bookings->hasConfirmedOverlap(
            $service->id,
            CarbonImmutable::parse('2026-09-15 10:30:00', 'UTC'),
            CarbonImmutable::parse('2026-09-15 11:00:00', 'UTC'),
        ));

        $this->assertFalse($bookings->hasConfirmedOverlap(
            $service->id,
            CarbonImmutable::parse('2026-09-15 11:00:00', 'UTC'),
            CarbonImmutable::parse('2026-09-15 11:30:00', 'UTC'),
        ));
    }
}

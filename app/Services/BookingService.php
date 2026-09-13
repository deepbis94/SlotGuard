<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Exceptions\AlreadyCancelledException;
use App\Exceptions\BookingOverlapException;
use App\Exceptions\CancellationWindowException;
use App\Models\Booking;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function create(Service $service, array $payload): Booking
    {
        return DB::transaction(function () use ($service, $payload) {
            $service = Service::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $startsAt = CarbonImmutable::parse($payload['starts_at'])->utc();
            $endsAt = $startsAt->addMinutes($service->duration_minutes);

            $this->assertStartsInTheFuture($startsAt);
            $this->assertWithinBusinessHours($service, $startsAt, $endsAt);

            if ($this->hasConfirmedOverlap($service->id, $startsAt, $endsAt)) {
                throw new BookingOverlapException;
            }

            try {
                return Booking::query()->create([
                    'service_id' => $service->id,
                    'customer_name' => $payload['customer_name'],
                    'customer_email' => $payload['customer_email'],
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => BookingStatus::Confirmed,
                ]);
            } catch (QueryException $e) {
                if ($this->isExclusionViolation($e)) {
                    throw new BookingOverlapException;
                }

                throw $e;
            }
        });
    }

    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->isCancelled()) {
                throw new AlreadyCancelledException;
            }

            $windowHours = (int) config('booking.cancellation_window_hours', 24);
            $deadline = $booking->starts_at->subHours($windowHours);

            if (CarbonImmutable::now('UTC')->gt($deadline)) {
                throw new CancellationWindowException(
                    "Bookings cannot be cancelled within {$windowHours} hours of the start time."
                );
            }

            $booking->status = BookingStatus::Cancelled;
            $booking->save();

            return $booking;
        });
    }

    public function hasConfirmedOverlap(int $serviceId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        // SQL equivalent of BookingInterval::overlaps() for half-open [start, end).
        return Booking::query()
            ->where('service_id', $serviceId)
            ->where('status', BookingStatus::Confirmed)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    private function assertStartsInTheFuture(CarbonImmutable $startsAt): void
    {
        if ($startsAt->lte(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages([
                'starts_at' => ['The start time must be in the future.'],
            ]);
        }
    }

    private function assertWithinBusinessHours(
        Service $service,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): void {
        $timezone = (string) config('booking.timezone', 'UTC');
        $localStart = $startsAt->timezone($timezone);
        $localEnd = $endsAt->timezone($timezone);

        $dailyStart = $this->timeToSeconds((string) $service->daily_start_time);
        $dailyEnd = $this->timeToSeconds((string) $service->daily_end_time);
        $startSeconds = $this->timeToSeconds($localStart->format('H:i:s'));
        $endSeconds = $this->timeToSeconds($localEnd->format('H:i:s'));

        if ($localStart->toDateString() !== $localEnd->toDateString()) {
            throw ValidationException::withMessages([
                'starts_at' => ['The appointment would end after the service\'s daily end time.'],
            ]);
        }

        if ($startSeconds < $dailyStart || $startSeconds >= $dailyEnd) {
            throw ValidationException::withMessages([
                'starts_at' => ['The appointment must start within the service\'s daily hours.'],
            ]);
        }

        if ($endSeconds > $dailyEnd) {
            throw ValidationException::withMessages([
                'starts_at' => ['The appointment would end after the service\'s daily end time.'],
            ]);
        }
    }

    private function timeToSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $time), 3, '0');

        return ((int) $hours * 3600) + ((int) $minutes * 60) + (int) $seconds;
    }

    private function isExclusionViolation(QueryException $e): bool
    {
        return $e->getCode() === '23P01'
            || str_contains(strtolower($e->getMessage()), 'exclusion');
    }
}

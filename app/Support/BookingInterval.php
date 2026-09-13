<?php

namespace App\Support;

use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Half-open time range [startsAt, endsAt).
 *
 * Adjacent bookings that share a boundary (one ends when the next starts)
 * do not overlap.
 */
final class BookingInterval
{
    public function __construct(
        public readonly CarbonInterface $startsAt,
        public readonly CarbonInterface $endsAt,
    ) {
        if ($this->endsAt->lte($this->startsAt)) {
            throw new InvalidArgumentException('Booking interval ends_at must be after starts_at.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt->lt($other->endsAt)
            && $this->endsAt->gt($other->startsAt);
    }
}

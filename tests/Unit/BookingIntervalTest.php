<?php

namespace Tests\Unit;

use App\Support\BookingInterval;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookingIntervalTest extends TestCase
{
    #[DataProvider('overlapCases')]
    public function test_half_open_overlap_rules(string $aStart, string $aEnd, string $bStart, string $bEnd, bool $expected): void
    {
        $a = new BookingInterval(
            CarbonImmutable::parse($aStart),
            CarbonImmutable::parse($aEnd),
        );
        $b = new BookingInterval(
            CarbonImmutable::parse($bStart),
            CarbonImmutable::parse($bEnd),
        );

        $this->assertSame($expected, $a->overlaps($b));
        $this->assertSame($expected, $b->overlaps($a));
    }

    public static function overlapCases(): array
    {
        return [
            'identical ranges overlap' => [
                '2026-09-15 10:00:00', '2026-09-15 10:30:00',
                '2026-09-15 10:00:00', '2026-09-15 10:30:00',
                true,
            ],
            'partial overlap' => [
                '2026-09-15 10:00:00', '2026-09-15 10:30:00',
                '2026-09-15 10:15:00', '2026-09-15 10:45:00',
                true,
            ],
            'contained range overlaps' => [
                '2026-09-15 10:00:00', '2026-09-15 11:00:00',
                '2026-09-15 10:15:00', '2026-09-15 10:45:00',
                true,
            ],
            'adjacent end-to-start does not overlap' => [
                '2026-09-15 10:00:00', '2026-09-15 10:30:00',
                '2026-09-15 10:30:00', '2026-09-15 11:00:00',
                false,
            ],
            'adjacent start-to-end does not overlap' => [
                '2026-09-15 11:00:00', '2026-09-15 11:30:00',
                '2026-09-15 10:30:00', '2026-09-15 11:00:00',
                false,
            ],
            'completely before does not overlap' => [
                '2026-09-15 09:00:00', '2026-09-15 09:30:00',
                '2026-09-15 10:00:00', '2026-09-15 10:30:00',
                false,
            ],
        ];
    }

    public function test_rejects_empty_or_inverted_interval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BookingInterval(
            CarbonImmutable::parse('2026-09-15 10:00:00'),
            CarbonImmutable::parse('2026-09-15 10:00:00'),
        );
    }
}

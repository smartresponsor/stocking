<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Service\StockReservationService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies reservation creation against immediate availability.
 */
final class StockReservationServiceTest extends TestCase
{
    public function testCreatesReservationAndHoldsAvailableQuantity(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5);

        $reservation = (new StockReservationService())->reserve(
            $level,
            'reservation-1',
            'retry-1',
            3,
            new \DateTimeImmutable('2026-09-21T12:05:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        self::assertSame(3, $reservation->quantity());
        self::assertSame(3, $level->reserved());
        self::assertSame(2, $level->available());
    }

    public function testRejectsAlreadyExpiredReservation(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5);

        $this->expectException(\InvalidArgumentException::class);

        (new StockReservationService())->reserve(
            $level,
            'reservation-1',
            'retry-1',
            1,
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
    }
}

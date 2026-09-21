<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Entity;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockReservationEntity;
use PHPUnit\Framework\TestCase;

/**
 * Verifies reservation lifecycle, expiry and replay-safe terminal transitions.
 */
final class StockReservationEntityTest extends TestCase
{
    public function testExposesReservationFacts(): void
    {
        $reservation = $this->reservation(quantity: 2);

        self::assertSame('reservation-1', $reservation->id());
        self::assertSame('retry-1', $reservation->idempotencyKey());
        self::assertSame('stock-1', $reservation->stockItemId());
        self::assertSame('loc-1', $reservation->locationReference());
        self::assertSame(2, $reservation->quantity());
        self::assertSame('2026-09-21T12:00:00+00:00', $reservation->expiresAt()->format(DATE_ATOM));
        self::assertTrue($reservation->isActive());
        self::assertSame(StockReservationEntity::STATUS_ACTIVE, $reservation->status());
    }

    public function testRejectsMismatchedLevel(): void
    {
        $reservation = $this->reservation(quantity: 1);
        $level = new StockLevelEntity('stock-other', 'loc-1', onHand: 1, reserved: 1);

        $this->expectException(\InvalidArgumentException::class);
        $reservation->release($level);
    }

    public function testReleaseIsReplaySafe(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5, reserved: 2);
        $reservation = $this->reservation(quantity: 2);

        self::assertTrue($reservation->release($level));
        self::assertFalse($reservation->release($level));
        self::assertSame(0, $level->reserved());
        self::assertSame(StockReservationEntity::STATUS_RELEASED, $reservation->status());
    }

    public function testExpiryReleasesOnlyAfterDeadlineAndIsReplaySafe(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5, reserved: 2);
        $reservation = $this->reservation(quantity: 2);

        self::assertFalse($reservation->expire(new \DateTimeImmutable('2026-09-21T11:59:59+00:00'), $level));
        self::assertTrue($reservation->expire(new \DateTimeImmutable('2026-09-21T12:00:00+00:00'), $level));
        self::assertFalse($reservation->expire(new \DateTimeImmutable('2026-09-21T12:01:00+00:00'), $level));
        self::assertSame(0, $level->reserved());
    }

    public function testConsumptionReducesBothReservedAndOnHand(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5, reserved: 2);
        $reservation = $this->reservation(quantity: 2);

        self::assertTrue($reservation->consume($level));
        self::assertFalse($reservation->consume($level));
        self::assertSame(3, $level->onHand());
        self::assertSame(0, $level->reserved());
    }

    public function testTerminalStateCannotChangeToDifferentTerminalState(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 5, reserved: 2);
        $reservation = $this->reservation(quantity: 2);
        $reservation->release($level);

        $this->expectException(\LogicException::class);
        $reservation->consume($level);
    }

    private function reservation(int $quantity): StockReservationEntity
    {
        return new StockReservationEntity(
            'reservation-1',
            'retry-1',
            'stock-1',
            'loc-1',
            $quantity,
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
    }
}

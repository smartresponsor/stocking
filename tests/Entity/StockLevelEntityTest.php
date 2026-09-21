<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Entity;

use App\Stocking\Entity\StockLevelEntity;
use PHPUnit\Framework\TestCase;

/**
 * Proves location-scoped stock quantity invariants and deterministic availability.
 */
final class StockLevelEntityTest extends TestCase
{
    public function testTracksOnHandReservedAvailableAndIncomingIndependently(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 10, reserved: 3, incoming: 5);
        self::assertSame(10, $level->onHand());
        self::assertSame(3, $level->reserved());
        self::assertSame(7, $level->available());
        self::assertSame(5, $level->incoming());
    }

    public function testQuantityLifecyclePreservesInvariants(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 10);
        $level->reserve(4);
        $level->release(1);
        $level->consumeReserved(2);
        $level->scheduleIncoming(5);
        $level->receiveIncoming(3);
        $level->adjustOnHand(12);
        self::assertSame(12, $level->onHand());
        self::assertSame(1, $level->reserved());
        self::assertSame(11, $level->available());
        self::assertSame(2, $level->incoming());
    }

    public function testExposesExternalIdentitiesAndDirectReceipt(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1');
        self::assertSame('stock-1', $level->stockItemId());
        self::assertSame('location-1', $level->locationReference());

        $level->receive(3);

        self::assertSame(3, $level->onHand());
        self::assertSame(3, $level->available());
    }

    public function testCannotReceiveIncomingBeyondScheduledQuantity(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', incoming: 2);

        $this->expectException(\InvalidArgumentException::class);
        $level->receiveIncoming(3);
    }

    public function testCannotReleaseMoreThanReserved(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 3, reserved: 1);

        $this->expectException(\InvalidArgumentException::class);
        $level->release(2);
    }

    public function testCannotConsumeMoreThanReserved(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 3, reserved: 1);

        $this->expectException(\InvalidArgumentException::class);
        $level->consumeReserved(2);
    }

    public function testRejectsBlankStockAndLocationIdentities(): void
    {
        try {
            new StockLevelEntity('', 'location-1');
            self::fail('Blank stock identity must fail.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        new StockLevelEntity('stock-1', ' ');
    }

    public function testCannotReserveMoreThanAvailable(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 2);
        $this->expectException(\InvalidArgumentException::class);
        $level->reserve(3);
    }

    public function testCannotConstructImpossibleQuantities(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StockLevelEntity('stock-1', 'location-1', onHand: 1, reserved: 2);
    }

    public function testCannotReduceOnHandBelowReservations(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 5, reserved: 4);
        $this->expectException(\InvalidArgumentException::class);
        $level->adjustOnHand(3);
    }
}

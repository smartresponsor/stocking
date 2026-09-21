<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Entity;

use App\Stocking\Entity\StockMovementEntity;
use PHPUnit\Framework\TestCase;

/**
 * Verifies immutable append-ledger movement facts.
 */
final class StockMovementEntityTest extends TestCase
{
    public function testStoresAuditableMovementFact(): void
    {
        $movement = new StockMovementEntity(
            'movement-1',
            'retry-1',
            'stock-1',
            'loc-1',
            StockMovementEntity::TYPE_ADJUSTMENT,
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            'reconciliation-17',
            onHandDelta: -2,
        );

        self::assertSame('movement-1', $movement->id);
        self::assertSame(-2, $movement->onHandDelta);
        self::assertSame(0, $movement->reservedDelta);
        self::assertSame(0, $movement->incomingDelta);
        self::assertSame('reconciliation-17', $movement->reference);
    }

    public function testRejectsZeroDelta(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StockMovementEntity(
            'movement-1',
            'retry-1',
            'stock-1',
            'loc-1',
            StockMovementEntity::TYPE_ADJUSTMENT,
            new \DateTimeImmutable(),
        );
    }
}

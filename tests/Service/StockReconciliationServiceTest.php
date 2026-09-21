<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Service\StockReconciliationService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies physical-count reconciliation and its immutable audit movement.
 */
final class StockReconciliationServiceTest extends TestCase
{
    public function testReconcilesQuantityAndProducesAdjustmentMovement(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 8, reserved: 2);

        $movement = (new StockReconciliationService())->reconcile(
            $level,
            6,
            'movement-1',
            'reconcile-1',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            'cycle-count-12',
        );

        self::assertNotNull($movement);
        self::assertSame(6, $level->onHand());
        self::assertSame(-2, $movement->onHandDelta);
        self::assertSame(0, $movement->reservedDelta);
        self::assertSame(StockMovementEntity::TYPE_ADJUSTMENT, $movement->type);
    }

    public function testNoChangeProducesNoMovement(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 8);

        self::assertNull((new StockReconciliationService())->reconcile(
            $level,
            8,
            'movement-1',
            'reconcile-1',
            new \DateTimeImmutable(),
        ));
    }

    public function testCannotReconcileBelowReservedQuantity(): void
    {
        $level = new StockLevelEntity('stock-1', 'loc-1', onHand: 8, reserved: 4);

        $this->expectException(\InvalidArgumentException::class);

        (new StockReconciliationService())->reconcile(
            $level,
            3,
            'movement-1',
            'reconcile-1',
            new \DateTimeImmutable(),
        );
    }
}

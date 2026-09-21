<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Service\StockTransferService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies multi-location transfers preserve reservations and emit paired audit facts.
 */
final class StockTransferServiceTest extends TestCase
{
    public function testTransfersAvailableStockAndProducesPairedMovements(): void
    {
        $source = new StockLevelEntity('stock-1', 'loc-a', onHand: 10, reserved: 3);
        $destination = new StockLevelEntity('stock-1', 'loc-b', onHand: 2);

        $movements = (new StockTransferService())->transfer(
            $source,
            $destination,
            4,
            'movement-out',
            'movement-in',
            'transfer-retry-1',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            'transfer-1',
        );

        self::assertSame(6, $source->onHand());
        self::assertSame(3, $source->reserved());
        self::assertSame(6, $destination->onHand());
        self::assertSame(-4, $movements['out']->onHandDelta);
        self::assertSame(4, $movements['in']->onHandDelta);
        self::assertSame(StockMovementEntity::TYPE_TRANSFER_OUT, $movements['out']->type);
        self::assertSame(StockMovementEntity::TYPE_TRANSFER_IN, $movements['in']->type);
    }

    public function testRejectsTransferThatWouldConsumeReservedStock(): void
    {
        $source = new StockLevelEntity('stock-1', 'loc-a', onHand: 10, reserved: 7);
        $destination = new StockLevelEntity('stock-1', 'loc-b');

        $this->expectException(\InvalidArgumentException::class);

        (new StockTransferService())->transfer(
            $source,
            $destination,
            4,
            'movement-out',
            'movement-in',
            'transfer-retry-1',
            new \DateTimeImmutable(),
            'transfer-1',
        );
    }

    public function testRejectsCrossItemTransfer(): void
    {
        $source = new StockLevelEntity('stock-a', 'loc-a', onHand: 10);
        $destination = new StockLevelEntity('stock-b', 'loc-b');

        $this->expectException(\InvalidArgumentException::class);

        (new StockTransferService())->transfer(
            $source,
            $destination,
            1,
            'movement-out',
            'movement-in',
            'transfer-retry-1',
            new \DateTimeImmutable(),
            'transfer-1',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Integration;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use App\Stocking\Repository\StockTransactionRepository;
use App\Stocking\Service\StockReconciliationPersistenceService;
use App\Stocking\Service\StockReconciliationService;
use App\Stocking\Service\StockSupplyPersistenceService;
use App\Stocking\Service\StockTransferPersistenceService;
use App\Stocking\Service\StockTransferService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Verifies durable transfer/reconciliation mutations and exact replay behavior.
 */
final class StockMovementPersistenceServiceTest extends TestCase
{
    public function testTransferReplayDoesNotMoveStockTwice(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-a', onHand: 10, reserved: 2));
        $em->persist(new StockLevelEntity('stock-1', 'loc-b', onHand: 1));
        $em->flush();

        $service = new StockTransferPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockMovementRepository($em),
            new StockTransferService(),
        );

        $first = $service->transfer(
            'stock-1',
            'loc-a',
            'loc-b',
            3,
            'movement-out-1',
            'movement-in-1',
            'transfer-retry-1',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            'transfer-1',
        );
        $replay = $service->transfer(
            'stock-1',
            'loc-a',
            'loc-b',
            3,
            'ignored-out',
            'ignored-in',
            'transfer-retry-1',
            new \DateTimeImmutable('2026-09-21T12:01:00+00:00'),
            'transfer-1',
        );

        self::assertSame($first['out']->id, $replay['out']->id);
        self::assertSame($first['in']->id, $replay['in']->id);

        $source = $em->find(StockLevelEntity::class, ['stockItemId' => 'stock-1', 'locationReference' => 'loc-a']);
        $destination = $em->find(StockLevelEntity::class, ['stockItemId' => 'stock-1', 'locationReference' => 'loc-b']);
        self::assertInstanceOf(StockLevelEntity::class, $source);
        self::assertInstanceOf(StockLevelEntity::class, $destination);
        self::assertSame(7, $source->onHand());
        self::assertSame(4, $destination->onHand());
    }

    public function testReconciliationReplayDoesNotApplyCountTwice(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-a', onHand: 10));
        $em->flush();

        $service = new StockReconciliationPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockMovementRepository($em),
            new StockReconciliationService(),
        );

        $first = $service->reconcile(
            'stock-1',
            'loc-a',
            8,
            'movement-1',
            'reconcile-retry-1',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
            'cycle-count-1',
        );
        $replay = $service->reconcile(
            'stock-1',
            'loc-a',
            5,
            'ignored-movement',
            'reconcile-retry-1',
            new \DateTimeImmutable('2026-09-21T12:05:00+00:00'),
            'cycle-count-1',
        );

        self::assertNotNull($first);
        self::assertNotNull($replay);
        self::assertSame($first->id, $replay->id);

        $level = $em->find(StockLevelEntity::class, ['stockItemId' => 'stock-1', 'locationReference' => 'loc-a']);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(8, $level->onHand());
    }

    public function testSupplyReplayPreservesBucketFactsAndDoesNotDoubleApply(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-a'));
        $em->flush();

        $service = new StockSupplyPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockMovementRepository($em),
        );

        $receipt = $service->receive(
            'stock-1',
            'loc-a',
            2,
            'receipt-1',
            'receipt-retry-1',
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
        $replayedReceipt = $service->receive(
            'stock-1',
            'loc-a',
            2,
            'ignored-receipt',
            'receipt-retry-1',
            new \DateTimeImmutable('2026-09-21T12:01:00+00:00'),
        );
        self::assertSame($receipt->id, $replayedReceipt->id);

        $scheduled = $service->scheduleIncoming(
            'stock-1',
            'loc-a',
            3,
            'incoming-1',
            'incoming-retry-1',
            new \DateTimeImmutable('2026-09-21T12:02:00+00:00'),
        );
        self::assertSame(3, $scheduled->incomingDelta);

        $received = $service->receiveIncoming(
            'stock-1',
            'loc-a',
            2,
            'incoming-receipt-1',
            'incoming-receipt-retry-1',
            new \DateTimeImmutable('2026-09-21T12:03:00+00:00'),
        );
        $replayedIncoming = $service->receiveIncoming(
            'stock-1',
            'loc-a',
            2,
            'ignored-incoming-receipt',
            'incoming-receipt-retry-1',
            new \DateTimeImmutable('2026-09-21T12:04:00+00:00'),
        );

        self::assertSame($received->id, $replayedIncoming->id);
        self::assertSame(2, $received->onHandDelta);
        self::assertSame(-2, $received->incomingDelta);

        $level = $em->find(StockLevelEntity::class, [
            'stockItemId' => 'stock-1',
            'locationReference' => 'loc-a',
        ]);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(4, $level->onHand());
        self::assertSame(1, $level->incoming());
        self::assertSame(4, $level->available());
    }

    public function testSupplyIdempotencyKeyRejectsDifferentQuantity(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-a'));
        $em->flush();

        $service = new StockSupplyPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockMovementRepository($em),
        );
        $service->receive(
            'stock-1',
            'loc-a',
            2,
            'receipt-1',
            'receipt-retry-1',
            new \DateTimeImmutable(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->receive(
            'stock-1',
            'loc-a',
            3,
            'receipt-2',
            'receipt-retry-1',
            new \DateTimeImmutable(),
        );
    }

    private function entityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);

        return new EntityManager($connection, $config);
    }
}

<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Integration;

use App\Stocking\Entity\StockItemEntity;
use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Entity\StockReservationEntity;
use App\Stocking\Repository\StockLevelRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Proves mapped idempotency constraints and optimistic lost-update protection.
 */
final class StockDoctrineMetadataTest extends TestCase
{
    public function testMappingDeclaresVersionAndUniqueIdempotencyKeys(): void
    {
        $em = $this->entityManager();

        $item = $em->getClassMetadata(StockItemEntity::class);
        $level = $em->getClassMetadata(StockLevelEntity::class);
        $reservation = $em->getClassMetadata(StockReservationEntity::class);
        $movement = $em->getClassMetadata(StockMovementEntity::class);

        self::assertSame('version', $level->versionField);
        self::assertArrayHasKey('uniq_stock_item_catalog_reference', $item->table['uniqueConstraints'] ?? []);
        self::assertArrayHasKey('uniq_stock_reservation_idempotency', $reservation->table['uniqueConstraints'] ?? []);
        self::assertArrayHasKey('uniq_stock_movement_idempotency', $movement->table['uniqueConstraints'] ?? []);
    }

    public function testStockLevelRepositoryPersistsAndReloadsLevel(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $repository = new StockLevelRepository($em);
        $repository->save(new StockLevelEntity('stock-1', 'loc-1', onHand: 4));
        $em->flush();
        $em->clear();

        $reloaded = $repository->find('stock-1', 'loc-1');

        self::assertInstanceOf(StockLevelEntity::class, $reloaded);
        self::assertSame(4, $reloaded->onHand());
    }

    public function testOptimisticVersionRejectsStaleParallelReservationWrite(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'stocking-');
        self::assertNotFalse($database);

        try {
            $first = $this->entityManager($database);
            (new SchemaTool($first))->createSchema($first->getMetadataFactory()->getAllMetadata());

            $first->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 1));
            $first->flush();
            $first->clear();

            $second = $this->entityManager($database);
            $levelA = $first->find(StockLevelEntity::class, ['stockItemId' => 'stock-1', 'locationReference' => 'loc-1']);
            $levelB = $second->find(StockLevelEntity::class, ['stockItemId' => 'stock-1', 'locationReference' => 'loc-1']);

            self::assertInstanceOf(StockLevelEntity::class, $levelA);
            self::assertInstanceOf(StockLevelEntity::class, $levelB);

            $levelA->reserve(1);
            $first->flush();

            $levelB->reserve(1);
            $this->expectException(OptimisticLockException::class);
            $second->flush();
        } finally {
            @unlink($database);
        }
    }

    private function entityManager(?string $database = null): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [dirname(__DIR__, 2).'/src/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $database ?? ':memory:',
        ], $config);

        return new EntityManager($connection, $config);
    }
}

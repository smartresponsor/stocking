<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Integration;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use App\Stocking\Repository\StockReservationRepository;
use App\Stocking\Repository\StockTransactionRepository;
use App\Stocking\Service\StockReservationPersistenceService;
use App\Stocking\Service\StockReservationService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Verifies durable reservation replay semantics against an actual database.
 */
final class StockReservationPersistenceServiceTest extends TestCase
{
    public function testExactReplayReturnsStoredReservationWithoutDoubleHoldingStock(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $em->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 2));
        $em->flush();

        $service = new StockReservationPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockReservationRepository($em),
            new StockMovementRepository($em),
            new StockReservationService(),
        );

        $first = $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-1',
            'retry-1',
            1,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
        $replayed = $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-other-id',
            'retry-1',
            1,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        self::assertSame($first->id(), $replayed->id());

        $level = $em->find(StockLevelEntity::class, [
            'stockItemId' => 'stock-1',
            'locationReference' => 'loc-1',
        ]);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(1, $level->reserved());
        self::assertSame(1, $level->available());
    }

    public function testIdempotencyKeyCannotBeReusedForDifferentCommand(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $em->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 3));
        $em->flush();

        $service = new StockReservationPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockReservationRepository($em),
            new StockMovementRepository($em),
            new StockReservationService(),
        );

        $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-1',
            'retry-1',
            1,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-2',
            'retry-1',
            2,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );
    }

    public function testReleaseReplayDoesNotReleaseReservedStockTwice(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 3));
        $em->flush();

        $service = $this->service($em);
        $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-1',
            'reserve-key',
            2,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        $released = $service->release(
            'reservation-1',
            'release-key',
            new \DateTimeImmutable('2026-09-21T12:10:00+00:00'),
        );
        $replayed = $service->release(
            'reservation-1',
            'release-key',
            new \DateTimeImmutable('2026-09-21T12:11:00+00:00'),
        );

        self::assertSame($released->id(), $replayed->id());
        self::assertSame('released', $released->status());

        $level = $em->find(StockLevelEntity::class, [
            'stockItemId' => 'stock-1',
            'locationReference' => 'loc-1',
        ]);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(0, $level->reserved());

        $movement = (new StockMovementRepository($em))
            ->findByIdempotencyKey(hash('sha256', 'release|release-key'));
        self::assertNotNull($movement);
        self::assertSame(-2, $movement->reservedDelta);
    }

    public function testConsumeReplayDoesNotConsumeStockTwice(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 4));
        $em->flush();

        $service = $this->service($em);
        $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-1',
            'reserve-key',
            2,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        $service->consume(
            'reservation-1',
            'consume-key',
            new \DateTimeImmutable('2026-09-21T12:10:00+00:00'),
        );
        $service->consume(
            'reservation-1',
            'consume-key',
            new \DateTimeImmutable('2026-09-21T12:11:00+00:00'),
        );

        $level = $em->find(StockLevelEntity::class, [
            'stockItemId' => 'stock-1',
            'locationReference' => 'loc-1',
        ]);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(2, $level->onHand());
        self::assertSame(0, $level->reserved());

        $movement = (new StockMovementRepository($em))
            ->findByIdempotencyKey(hash('sha256', 'consume|consume-key'));
        self::assertNotNull($movement);
        self::assertSame(-2, $movement->onHandDelta);
        self::assertSame(-2, $movement->reservedDelta);
    }

    public function testExpiryBeforeDeadlineDoesNothingThenExpiresExactlyOnce(): void
    {
        $em = $this->entityManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $em->persist(new StockLevelEntity('stock-1', 'loc-1', onHand: 2));
        $em->flush();

        $service = $this->service($em);
        $service->reserve(
            'stock-1',
            'loc-1',
            'reservation-1',
            'reserve-key',
            1,
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
            new \DateTimeImmutable('2026-09-21T12:00:00+00:00'),
        );

        self::assertFalse($service->expire(
            'reservation-1',
            'expire-key',
            new \DateTimeImmutable('2026-09-21T12:59:59+00:00'),
        ));
        self::assertTrue($service->expire(
            'reservation-1',
            'expire-key',
            new \DateTimeImmutable('2026-09-21T13:00:00+00:00'),
        ));
        self::assertTrue($service->expire(
            'reservation-1',
            'expire-key',
            new \DateTimeImmutable('2026-09-21T13:01:00+00:00'),
        ));

        $level = $em->find(StockLevelEntity::class, [
            'stockItemId' => 'stock-1',
            'locationReference' => 'loc-1',
        ]);
        self::assertInstanceOf(StockLevelEntity::class, $level);
        self::assertSame(0, $level->reserved());
    }

    private function service(EntityManager $em): StockReservationPersistenceService
    {
        return new StockReservationPersistenceService(
            new StockTransactionRepository($em),
            new StockLevelRepository($em),
            new StockReservationRepository($em),
            new StockMovementRepository($em),
            new StockReservationService(),
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

<?php

declare(strict_types=1);

namespace App\Stocking\Repository;

use App\Stocking\Entity\StockReservationEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provides durable reservation lookup and persistence.
 */
final class StockReservationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** Finds one reservation by Stocking identity. */
    public function find(string $id): ?StockReservationEntity
    {
        $reservation = $this->entityManager->find(StockReservationEntity::class, $id);

        return $reservation instanceof StockReservationEntity ? $reservation : null;
    }

    /** Finds a reservation by caller-owned retry identity. */
    public function findByIdempotencyKey(string $idempotencyKey): ?StockReservationEntity
    {
        $reservation = $this->entityManager->getRepository(StockReservationEntity::class)
            ->findOneBy(['idempotencyKey' => $idempotencyKey]);

        return $reservation instanceof StockReservationEntity ? $reservation : null;
    }

    /** Schedules a reservation for persistence. */
    public function save(StockReservationEntity $reservation): void
    {
        $this->entityManager->persist($reservation);
    }
}

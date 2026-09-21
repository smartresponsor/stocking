<?php

declare(strict_types=1);

namespace App\Stocking\Repository;

use App\Stocking\Entity\StockMovementEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists immutable append-oriented movement facts.
 */
final class StockMovementRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** Finds an already-appended movement by retry identity. */
    public function findByIdempotencyKey(string $idempotencyKey): ?StockMovementEntity
    {
        $movement = $this->entityManager->getRepository(StockMovementEntity::class)
            ->findOneBy(['idempotencyKey' => $idempotencyKey]);

        return $movement instanceof StockMovementEntity ? $movement : null;
    }

    /** Appends a movement fact to the current unit of work. */
    public function append(StockMovementEntity $movement): void
    {
        $this->entityManager->persist($movement);
    }
}

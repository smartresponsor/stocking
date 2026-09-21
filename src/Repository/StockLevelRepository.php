<?php

declare(strict_types=1);

namespace App\Stocking\Repository;

use App\Stocking\Entity\StockLevelEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists and loads location-scoped stock levels.
 */
final class StockLevelRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /** Finds one stock level by Stocking item and opaque location identities. */
    public function find(string $stockItemId, string $locationReference): ?StockLevelEntity
    {
        $level = $this->entityManager->find(StockLevelEntity::class, [
            'stockItemId' => $stockItemId,
            'locationReference' => $locationReference,
        ]);

        return $level instanceof StockLevelEntity ? $level : null;
    }

    /** Schedules a stock level for persistence. */
    public function save(StockLevelEntity $level): void
    {
        $this->entityManager->persist($level);
    }
}

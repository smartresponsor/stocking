<?php

declare(strict_types=1);

namespace App\Stocking\Repository;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns the Doctrine transaction boundary used by Stocking persistence operations.
 */
final class StockTransactionRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Executes one short persistence operation atomically.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $this->entityManager->wrapInTransaction($operation);
    }
}

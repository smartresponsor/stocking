<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists reconciliation adjustments and their immutable movement evidence atomically.
 */
final class StockReconciliationPersistenceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockLevelRepository $levelRepository,
        private readonly StockMovementRepository $movementRepository,
        private readonly StockReconciliationService $reconciliationService,
    ) {
    }

    /** Replays an existing adjustment or applies a new physical-count reconciliation. */
    public function reconcile(
        string $stockItemId,
        string $locationReference,
        int $countedOnHand,
        string $movementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        ?string $reference = null,
    ): ?StockMovementEntity {
        return $this->entityManager->wrapInTransaction(function () use (
            $stockItemId,
            $locationReference,
            $countedOnHand,
            $movementId,
            $idempotencyKey,
            $occurredAt,
            $reference,
        ): ?StockMovementEntity {
            $movementKey = hash('sha256', 'reconcile|'.$idempotencyKey);
            $existing = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existing instanceof StockMovementEntity) {
                return $existing;
            }

            $level = $this->levelRepository->find($stockItemId, $locationReference);
            if (null === $level) {
                throw new \RuntimeException('Stock level does not exist.');
            }

            $movement = $this->reconciliationService->reconcile(
                $level,
                $countedOnHand,
                $movementId,
                $movementKey,
                $occurredAt,
                $reference,
            );

            if ($movement instanceof StockMovementEntity) {
                $this->movementRepository->append($movement);
            }

            return $movement;
        });
    }
}

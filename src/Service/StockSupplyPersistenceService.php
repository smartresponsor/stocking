<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persists supply-side stock mutations and immutable ledger evidence atomically.
 */
final class StockSupplyPersistenceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockLevelRepository $levelRepository,
        private readonly StockMovementRepository $movementRepository,
    ) {
    }

    /** Receives physical stock into on-hand and records an idempotent receipt movement. */
    public function receive(
        string $stockItemId,
        string $locationReference,
        int $quantity,
        string $movementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        ?string $reference = null,
    ): StockMovementEntity {
        return $this->entityManager->wrapInTransaction(function () use (
            $stockItemId,
            $locationReference,
            $quantity,
            $movementId,
            $idempotencyKey,
            $occurredAt,
            $reference,
        ): StockMovementEntity {
            $movementKey = self::movementKey('receipt', $idempotencyKey);
            $existing = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existing instanceof StockMovementEntity) {
                $this->assertReplayMatches($existing, $stockItemId, $locationReference, $quantity, 0);

                return $existing;
            }

            $level = $this->requireLevel($stockItemId, $locationReference);
            $level->receive($quantity);

            $movement = new StockMovementEntity(
                id: $movementId,
                idempotencyKey: $movementKey,
                stockItemId: $stockItemId,
                locationReference: $locationReference,
                type: StockMovementEntity::TYPE_RECEIPT,
                occurredAt: $occurredAt,
                reference: $reference,
                onHandDelta: $quantity,
            );
            $this->movementRepository->append($movement);

            return $movement;
        });
    }

    /** Schedules replenishment into incoming without exposing it as immediately available. */
    public function scheduleIncoming(
        string $stockItemId,
        string $locationReference,
        int $quantity,
        string $movementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        ?string $reference = null,
    ): StockMovementEntity {
        return $this->entityManager->wrapInTransaction(function () use (
            $stockItemId,
            $locationReference,
            $quantity,
            $movementId,
            $idempotencyKey,
            $occurredAt,
            $reference,
        ): StockMovementEntity {
            $movementKey = self::movementKey('incoming-scheduled', $idempotencyKey);
            $existing = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existing instanceof StockMovementEntity) {
                $this->assertReplayMatches($existing, $stockItemId, $locationReference, 0, $quantity);

                return $existing;
            }

            $level = $this->requireLevel($stockItemId, $locationReference);
            $level->scheduleIncoming($quantity);

            $movement = new StockMovementEntity(
                id: $movementId,
                idempotencyKey: $movementKey,
                stockItemId: $stockItemId,
                locationReference: $locationReference,
                type: StockMovementEntity::TYPE_INCOMING_SCHEDULED,
                occurredAt: $occurredAt,
                reference: $reference,
                incomingDelta: $quantity,
            );
            $this->movementRepository->append($movement);

            return $movement;
        });
    }

    /** Converts incoming stock into on-hand and records both bucket deltas. */
    public function receiveIncoming(
        string $stockItemId,
        string $locationReference,
        int $quantity,
        string $movementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        ?string $reference = null,
    ): StockMovementEntity {
        return $this->entityManager->wrapInTransaction(function () use (
            $stockItemId,
            $locationReference,
            $quantity,
            $movementId,
            $idempotencyKey,
            $occurredAt,
            $reference,
        ): StockMovementEntity {
            $movementKey = self::movementKey('incoming-receipt', $idempotencyKey);
            $existing = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existing instanceof StockMovementEntity) {
                $this->assertReplayMatches($existing, $stockItemId, $locationReference, $quantity, -$quantity);

                return $existing;
            }

            $level = $this->requireLevel($stockItemId, $locationReference);
            $level->receiveIncoming($quantity);

            $movement = new StockMovementEntity(
                id: $movementId,
                idempotencyKey: $movementKey,
                stockItemId: $stockItemId,
                locationReference: $locationReference,
                type: StockMovementEntity::TYPE_INCOMING_RECEIPT,
                occurredAt: $occurredAt,
                reference: $reference,
                onHandDelta: $quantity,
                incomingDelta: -$quantity,
            );
            $this->movementRepository->append($movement);

            return $movement;
        });
    }

    private function requireLevel(string $stockItemId, string $locationReference): \App\Stocking\Entity\StockLevelEntity
    {
        $level = $this->levelRepository->find($stockItemId, $locationReference);
        if (null === $level) {
            throw new \RuntimeException('Stock level does not exist.');
        }

        return $level;
    }

    private function assertReplayMatches(
        StockMovementEntity $movement,
        string $stockItemId,
        string $locationReference,
        int $onHandDelta,
        int $incomingDelta,
    ): void {
        if (
            $movement->stockItemId !== $stockItemId
            || $movement->locationReference !== $locationReference
            || $movement->onHandDelta !== $onHandDelta
            || $movement->incomingDelta !== $incomingDelta
        ) {
            throw new \InvalidArgumentException('Idempotency key was already used for a different supply command.');
        }
    }

    private static function movementKey(string $action, string $idempotencyKey): string
    {
        return hash('sha256', $action.'|'.$idempotencyKey);
    }
}

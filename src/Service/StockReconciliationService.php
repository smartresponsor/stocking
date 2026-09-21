<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockMovementEntity;

/**
 * Reconciles counted stock with recorded on-hand quantity and emits an audit fact.
 */
final class StockReconciliationService
{
    /** Reconciles one level and returns null when the physical count does not change stock. */
    public function reconcile(
        StockLevelEntity $level,
        int $countedOnHand,
        string $movementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        ?string $reference = null,
    ): ?StockMovementEntity {
        $delta = $countedOnHand - $level->onHand();

        if (0 === $delta) {
            return null;
        }

        $level->adjustOnHand($countedOnHand);

        return new StockMovementEntity(
            id: $movementId,
            idempotencyKey: $idempotencyKey,
            stockItemId: $level->stockItemId(),
            locationReference: $level->locationReference(),
            type: StockMovementEntity::TYPE_ADJUSTMENT,
            occurredAt: $occurredAt,
            onHandDelta: $delta,
            reference: $reference,
        );
    }
}

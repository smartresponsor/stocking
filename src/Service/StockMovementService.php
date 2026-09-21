<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockMovementEntity;

/**
 * Creates append-oriented immutable stock movement facts.
 */
final class StockMovementService
{
    /** Creates a normalized movement fact without mutating historical movements. */
    public function create(
        string $id,
        string $idempotencyKey,
        string $stockItemId,
        string $locationReference,
        string $type,
        \DateTimeImmutable $occurredAt,
        int $onHandDelta = 0,
        int $reservedDelta = 0,
        int $incomingDelta = 0,
        ?string $reference = null,
    ): StockMovementEntity {
        return new StockMovementEntity(
            id: $id,
            idempotencyKey: $idempotencyKey,
            stockItemId: $stockItemId,
            locationReference: $locationReference,
            type: $type,
            occurredAt: $occurredAt,
            onHandDelta: $onHandDelta,
            reservedDelta: $reservedDelta,
            incomingDelta: $incomingDelta,
            reference: $reference,
        );
    }
}

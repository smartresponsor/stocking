<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockReservationEntity;

/**
 * Creates inventory reservations after validating immediate availability.
 */
final class StockReservationService
{
    /**
     * Reserves quantity atomically only with respect to the supplied in-memory level instance.
     *
     * Durable callers must enforce unique idempotency keys and serialization in persistence.
     */
    public function reserve(
        StockLevelEntity $level,
        string $reservationId,
        string $idempotencyKey,
        int $quantity,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): StockReservationEntity {
        if ($expiresAt <= $now) {
            throw new \InvalidArgumentException('Reservation expiry must be in the future.');
        }

        $level->reserve($quantity);

        return new StockReservationEntity(
            id: $reservationId,
            idempotencyKey: $idempotencyKey,
            stockItemId: $level->stockItemId(),
            locationReference: $level->locationReference(),
            quantity: $quantity,
            expiresAt: $expiresAt,
        );
    }
}

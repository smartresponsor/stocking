<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Entity\StockReservationEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use App\Stocking\Repository\StockReservationRepository;
use App\Stocking\Repository\StockTransactionRepository;

/**
 * Applies durable idempotent reservation commands inside one Doctrine transaction.
 */
final class StockReservationPersistenceService
{
    public function __construct(
        private readonly StockTransactionRepository $transactionRepository,
        private readonly StockLevelRepository $levelRepository,
        private readonly StockReservationRepository $reservationRepository,
        private readonly StockMovementRepository $movementRepository,
        private readonly StockReservationService $reservationService,
    ) {
    }

    /**
     * Returns the existing reservation for an exact replay or creates one transactionally.
     *
     * Optimistic locking on StockLevel rejects stale parallel writers.
     */
    public function reserve(
        string $stockItemId,
        string $locationReference,
        string $reservationId,
        string $idempotencyKey,
        int $quantity,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $now,
    ): StockReservationEntity {
        return $this->transactionRepository->transactional(function () use (
            $stockItemId,
            $locationReference,
            $reservationId,
            $idempotencyKey,
            $quantity,
            $expiresAt,
            $now,
        ): StockReservationEntity {
            $existing = $this->reservationRepository->findByIdempotencyKey($idempotencyKey);
            if ($existing instanceof StockReservationEntity) {
                $this->assertReplayMatches(
                    $existing,
                    $reservationId,
                    $stockItemId,
                    $locationReference,
                    $quantity,
                    $expiresAt,
                );

                return $existing;
            }

            $level = $this->levelRepository->find($stockItemId, $locationReference);
            if (null === $level) {
                throw new \RuntimeException('Stock level does not exist.');
            }

            $reservation = $this->reservationService->reserve(
                $level,
                $reservationId,
                $idempotencyKey,
                $quantity,
                $expiresAt,
                $now,
            );
            $this->reservationRepository->save($reservation);
            $this->movementRepository->append(new StockMovementEntity(
                id: hash('sha256', 'reservation|'.$reservationId),
                idempotencyKey: hash('sha256', 'reservation|'.$idempotencyKey),
                stockItemId: $stockItemId,
                locationReference: $locationReference,
                type: StockMovementEntity::TYPE_RESERVATION,
                occurredAt: $now,
                reference: $reservationId,
                reservedDelta: $quantity,
            ));

            return $reservation;
        });
    }

    /** Releases a reservation and records the reserved-bucket delta atomically. */
    public function release(
        string $reservationId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
    ): StockReservationEntity {
        return $this->transactionRepository->transactional(function () use (
            $reservationId,
            $idempotencyKey,
            $occurredAt,
        ): StockReservationEntity {
            $movementKey = self::movementKey('release', $idempotencyKey);
            $existingMovement = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existingMovement instanceof StockMovementEntity) {
                $this->assertMovementReplayMatches($existingMovement, $reservationId);

                return $this->requireReservation($reservationId);
            }

            $reservation = $this->requireReservation($reservationId);
            $level = $this->requireLevel($reservation);
            if (!$reservation->release($level)) {
                return $reservation;
            }

            $this->movementRepository->append(new StockMovementEntity(
                id: hash('sha256', 'release|'.$reservationId.'|'.$idempotencyKey),
                idempotencyKey: $movementKey,
                stockItemId: $reservation->stockItemId(),
                locationReference: $reservation->locationReference(),
                type: StockMovementEntity::TYPE_RELEASE,
                occurredAt: $occurredAt,
                reference: $reservationId,
                reservedDelta: -$reservation->quantity(),
            ));

            return $reservation;
        });
    }

    /** Consumes a reservation and records on-hand plus reserved bucket deltas atomically. */
    public function consume(
        string $reservationId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
    ): StockReservationEntity {
        return $this->transactionRepository->transactional(function () use (
            $reservationId,
            $idempotencyKey,
            $occurredAt,
        ): StockReservationEntity {
            $movementKey = self::movementKey('consume', $idempotencyKey);
            $existingMovement = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existingMovement instanceof StockMovementEntity) {
                $this->assertMovementReplayMatches($existingMovement, $reservationId);

                return $this->requireReservation($reservationId);
            }

            $reservation = $this->requireReservation($reservationId);
            $level = $this->requireLevel($reservation);
            if (!$reservation->consume($level)) {
                return $reservation;
            }

            $this->movementRepository->append(new StockMovementEntity(
                id: hash('sha256', 'consume|'.$reservationId.'|'.$idempotencyKey),
                idempotencyKey: $movementKey,
                stockItemId: $reservation->stockItemId(),
                locationReference: $reservation->locationReference(),
                type: StockMovementEntity::TYPE_CONSUMPTION,
                occurredAt: $occurredAt,
                reference: $reservationId,
                onHandDelta: -$reservation->quantity(),
                reservedDelta: -$reservation->quantity(),
            ));

            return $reservation;
        });
    }

    /** Expires an eligible reservation and records the released reserved quantity atomically. */
    public function expire(
        string $reservationId,
        string $idempotencyKey,
        \DateTimeImmutable $now,
    ): bool {
        return $this->transactionRepository->transactional(function () use (
            $reservationId,
            $idempotencyKey,
            $now,
        ): bool {
            $movementKey = self::movementKey('expire', $idempotencyKey);
            $existingMovement = $this->movementRepository->findByIdempotencyKey($movementKey);
            if ($existingMovement instanceof StockMovementEntity) {
                $this->assertMovementReplayMatches($existingMovement, $reservationId);

                return true;
            }

            $reservation = $this->requireReservation($reservationId);
            $level = $this->requireLevel($reservation);
            if (!$reservation->expire($now, $level)) {
                return false;
            }

            $this->movementRepository->append(new StockMovementEntity(
                id: hash('sha256', 'expire|'.$reservationId.'|'.$idempotencyKey),
                idempotencyKey: $movementKey,
                stockItemId: $reservation->stockItemId(),
                locationReference: $reservation->locationReference(),
                type: StockMovementEntity::TYPE_RELEASE,
                occurredAt: $now,
                reference: $reservationId,
                reservedDelta: -$reservation->quantity(),
            ));

            return true;
        });
    }

    private function requireReservation(string $reservationId): StockReservationEntity
    {
        $reservation = $this->reservationRepository->find($reservationId);
        if (!$reservation instanceof StockReservationEntity) {
            throw new \RuntimeException('Stock reservation does not exist.');
        }

        return $reservation;
    }

    private function requireLevel(StockReservationEntity $reservation): \App\Stocking\Entity\StockLevelEntity
    {
        $level = $this->levelRepository->find(
            $reservation->stockItemId(),
            $reservation->locationReference(),
        );
        if (null === $level) {
            throw new \RuntimeException('Stock level does not exist.');
        }

        return $level;
    }

    private function assertMovementReplayMatches(StockMovementEntity $movement, string $reservationId): void
    {
        if ($movement->reference !== $reservationId) {
            throw new \InvalidArgumentException('Idempotency key was already used for a different reservation command.');
        }
    }

    private static function movementKey(string $action, string $idempotencyKey): string
    {
        return hash('sha256', $action.'|'.$idempotencyKey);
    }

    private function assertReplayMatches(
        StockReservationEntity $existing,
        string $reservationId,
        string $stockItemId,
        string $locationReference,
        int $quantity,
        \DateTimeImmutable $expiresAt,
    ): void {
        if (
            $existing->id() !== $reservationId
            || $existing->stockItemId() !== $stockItemId
            || $existing->locationReference() !== $locationReference
            || $existing->quantity() !== $quantity
            || $existing->expiresAt() != $expiresAt
        ) {
            throw new \InvalidArgumentException('Idempotency key was already used for a different reservation command.');
        }
    }
}

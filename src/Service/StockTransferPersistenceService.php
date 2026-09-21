<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockMovementEntity;
use App\Stocking\Repository\StockLevelRepository;
use App\Stocking\Repository\StockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies idempotent multi-location transfers in one optimistic-locking transaction.
 */
final class StockTransferPersistenceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockLevelRepository $levelRepository,
        private readonly StockMovementRepository $movementRepository,
        private readonly StockTransferService $transferService,
    ) {
    }

    /** @return array{out: StockMovementEntity, in: StockMovementEntity} */
    public function transfer(
        string $stockItemId,
        string $sourceLocationReference,
        string $destinationLocationReference,
        int $quantity,
        string $outMovementId,
        string $inMovementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        string $transferReference,
    ): array {
        return $this->entityManager->wrapInTransaction(function () use (
            $stockItemId,
            $sourceLocationReference,
            $destinationLocationReference,
            $quantity,
            $outMovementId,
            $inMovementId,
            $idempotencyKey,
            $occurredAt,
            $transferReference,
        ): array {
            $outKey = self::movementKey('transfer-out', $idempotencyKey);
            $inKey = self::movementKey('transfer-in', $idempotencyKey);
            $existingOut = $this->movementRepository->findByIdempotencyKey($outKey);
            $existingIn = $this->movementRepository->findByIdempotencyKey($inKey);

            if ($existingOut instanceof StockMovementEntity || $existingIn instanceof StockMovementEntity) {
                if (!$existingOut instanceof StockMovementEntity || !$existingIn instanceof StockMovementEntity) {
                    throw new \RuntimeException('Transfer ledger is incomplete for an existing idempotency key.');
                }

                return ['out' => $existingOut, 'in' => $existingIn];
            }

            $source = $this->levelRepository->find($stockItemId, $sourceLocationReference);
            $destination = $this->levelRepository->find($stockItemId, $destinationLocationReference);
            if (null === $source || null === $destination) {
                throw new \RuntimeException('Transfer source and destination stock levels must exist.');
            }

            $movements = $this->transferService->transfer(
                $source,
                $destination,
                $quantity,
                $outMovementId,
                $inMovementId,
                $idempotencyKey,
                $occurredAt,
                $transferReference,
            );

            $movements['out'] = new StockMovementEntity(
                id: $movements['out']->id,
                idempotencyKey: $outKey,
                stockItemId: $movements['out']->stockItemId,
                locationReference: $movements['out']->locationReference,
                type: $movements['out']->type,
                occurredAt: $movements['out']->occurredAt,
                reference: $movements['out']->reference,
                onHandDelta: $movements['out']->onHandDelta,
            );
            $movements['in'] = new StockMovementEntity(
                id: $movements['in']->id,
                idempotencyKey: $inKey,
                stockItemId: $movements['in']->stockItemId,
                locationReference: $movements['in']->locationReference,
                type: $movements['in']->type,
                occurredAt: $movements['in']->occurredAt,
                reference: $movements['in']->reference,
                onHandDelta: $movements['in']->onHandDelta,
            );

            $this->movementRepository->append($movements['out']);
            $this->movementRepository->append($movements['in']);

            return $movements;
        });
    }

    private static function movementKey(string $type, string $idempotencyKey): string
    {
        return hash('sha256', $type.'|'.$idempotencyKey);
    }
}

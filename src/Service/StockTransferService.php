<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Entity\StockMovementEntity;

/**
 * Transfers immediately available stock between two external locations for the same item.
 */
final class StockTransferService
{
    /**
     * @return array{out: StockMovementEntity, in: StockMovementEntity}
     */
    public function transfer(
        StockLevelEntity $source,
        StockLevelEntity $destination,
        int $quantity,
        string $outMovementId,
        string $inMovementId,
        string $idempotencyKey,
        \DateTimeImmutable $occurredAt,
        string $transferReference,
    ): array {
        if ($source->stockItemId() !== $destination->stockItemId()) {
            throw new \InvalidArgumentException('Transfer stock item identities must match.');
        }
        if ($source->locationReference() === $destination->locationReference()) {
            throw new \InvalidArgumentException('Transfer source and destination locations must differ.');
        }
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Transfer quantity must be greater than zero.');
        }
        if ($quantity > $source->available()) {
            throw new \InvalidArgumentException('Transfer exceeds source available stock.');
        }

        $source->adjustOnHand($source->onHand() - $quantity);
        $destination->receive($quantity);

        return [
            'out' => new StockMovementEntity(
                id: $outMovementId,
                idempotencyKey: $idempotencyKey.':out',
                stockItemId: $source->stockItemId(),
                locationReference: $source->locationReference(),
                type: StockMovementEntity::TYPE_TRANSFER_OUT,
                occurredAt: $occurredAt,
                onHandDelta: -$quantity,
                reference: $transferReference,
            ),
            'in' => new StockMovementEntity(
                id: $inMovementId,
                idempotencyKey: $idempotencyKey.':in',
                stockItemId: $destination->stockItemId(),
                locationReference: $destination->locationReference(),
                type: StockMovementEntity::TYPE_TRANSFER_IN,
                occurredAt: $occurredAt,
                onHandDelta: $quantity,
                reference: $transferReference,
            ),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Policy\StockPromisePolicy;

/**
 * Provides deterministic availability calculations over Stocking-owned quantity facts.
 */
final class StockAvailabilityService
{
    /** Calculates baseline ATP without treating incoming stock as present. */
    public function availableToPromise(StockLevelEntity $stockLevel): int
    {
        return $stockLevel->available();
    }

    /** Calculates policy-aware promise capacity without mutating physical inventory facts. */
    public function promisableQuantity(StockLevelEntity $stockLevel, StockPromisePolicy $policy): int
    {
        $promisable = $stockLevel->available();
        if ($policy->includeIncoming) {
            $promisable += $stockLevel->incoming();
        }

        return $promisable + $policy->backorderLimit;
    }

    /** Reports whether a requested quantity can be promised under the supplied policy. */
    public function canPromise(StockLevelEntity $stockLevel, int $quantity, StockPromisePolicy $policy): bool
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Promise quantity must be greater than zero.');
        }

        return $quantity <= $this->promisableQuantity($stockLevel, $policy);
    }

    /** Returns the portion of a promise that exceeds immediately available physical stock. */
    public function deferredQuantity(StockLevelEntity $stockLevel, int $quantity): int
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Promise quantity must be greater than zero.');
        }

        return max(0, $quantity - $stockLevel->available());
    }
}

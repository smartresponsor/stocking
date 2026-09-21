<?php

declare(strict_types=1);

namespace App\Stocking\Service;

use App\Stocking\Entity\StockLevelEntity;

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
}

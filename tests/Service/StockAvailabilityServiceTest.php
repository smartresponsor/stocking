<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Service\StockAvailabilityService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the deterministic available-to-promise baseline calculation.
 */
final class StockAvailabilityServiceTest extends TestCase
{
    public function testCalculatesAvailableToPromiseFromOnHandMinusReserved(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 11, reserved: 4, incoming: 20);
        self::assertSame(7, (new StockAvailabilityService())->availableToPromise($level));
    }
}

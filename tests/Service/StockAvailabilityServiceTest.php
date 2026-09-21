<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Service;

use App\Stocking\Entity\StockLevelEntity;
use App\Stocking\Policy\StockPromisePolicy;
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

    public function testStrictPolicyDoesNotCountIncomingOrBackorder(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 11, reserved: 4, incoming: 20);
        $service = new StockAvailabilityService();

        self::assertSame(7, $service->promisableQuantity($level, StockPromisePolicy::strict()));
        self::assertTrue($service->canPromise($level, 7, StockPromisePolicy::strict()));
        self::assertFalse($service->canPromise($level, 8, StockPromisePolicy::strict()));
    }

    public function testIncomingPolicyExtendsPromiseWithoutChangingImmediateAvailability(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 5, reserved: 2, incoming: 4);
        $service = new StockAvailabilityService();

        self::assertSame(3, $service->availableToPromise($level));
        self::assertSame(7, $service->promisableQuantity($level, StockPromisePolicy::withIncoming()));
        self::assertSame(3, $service->deferredQuantity($level, 6));
        self::assertSame(5, $level->onHand());
        self::assertSame(4, $level->incoming());
    }

    public function testBoundedBackorderExtendsPromiseWithoutViolatingStockLevelInvariant(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 2, reserved: 2);
        $service = new StockAvailabilityService();
        $policy = StockPromisePolicy::withBackorderLimit(3);

        self::assertSame(3, $service->promisableQuantity($level, $policy));
        self::assertTrue($service->canPromise($level, 3, $policy));
        self::assertFalse($service->canPromise($level, 4, $policy));
        self::assertSame(0, $level->available());
        self::assertSame(2, $level->reserved());
    }

    public function testRejectsNegativeBackorderLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StockPromisePolicy(backorderLimit: -1);
    }

    public function testDeferredQuantityIsZeroWhenImmediateStockCoversPromise(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1', onHand: 4);

        self::assertSame(0, (new StockAvailabilityService())->deferredQuantity($level, 3));
    }

    public function testRejectsNonPositivePromiseQuantity(): void
    {
        $level = new StockLevelEntity('stock-1', 'location-1');
        $service = new StockAvailabilityService();

        try {
            $service->canPromise($level, 0, StockPromisePolicy::strict());
            self::fail('Non-positive canPromise quantity must fail.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->deferredQuantity($level, 0);
    }
}

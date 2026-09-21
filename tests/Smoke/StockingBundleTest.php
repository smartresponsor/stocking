<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Smoke;

use App\Stocking\StockingBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Verifies the reusable Stocking bundle surface without introducing product behavior.
 */
final class StockingBundleTest extends TestCase
{
    /**
     * Confirms the Symfony bundle contract required for host composition.
     */
    public function testBundleSurface(): void
    {
        self::assertInstanceOf(Bundle::class, new StockingBundle());
    }
}

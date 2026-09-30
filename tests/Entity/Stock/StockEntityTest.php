<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Entity\Stock;

use App\Stocking\Entity\Stock\StockEntity;
use PHPUnit\Framework\TestCase;

final class StockEntityTest extends TestCase
{
    public function testExposesRepositoryOwnedIdentity(): void
    {
        $stock = new StockEntity('stock-main');

        self::assertSame('stock-main', $stock->id());
    }

    public function testRejectsEmptyIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stock id must not be empty.');

        new StockEntity('   ');
    }
}

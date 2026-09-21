<?php

declare(strict_types=1);

namespace App\Stocking\Tests\Entity;

use App\Stocking\Entity\StockItemEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies inventory identity without assuming ownership of catalog product data.
 */
final class StockItemEntityTest extends TestCase
{
    public function testStoresInventoryAndCatalogReferences(): void
    {
        $item = new StockItemEntity('stock-item-1', 'catalog-product-42');
        self::assertSame('stock-item-1', $item->id());
        self::assertSame('catalog-product-42', $item->catalogReference());
    }

    #[DataProvider('invalidIdentityProvider')]
    public function testRejectsBlankIdentity(string $id, string $catalogReference): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StockItemEntity($id, $catalogReference);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIdentityProvider(): iterable
    {
        yield 'stock item' => ['', 'catalog-1'];
        yield 'catalog reference' => ['stock-1', '   '];
    }
}

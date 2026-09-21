<?php

declare(strict_types=1);

namespace App\Stocking\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Identifies one inventory-managed item while keeping catalog identity external.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock_item')]
final class StockItemEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        private readonly string $id,
        #[ORM\Column(name: 'catalog_reference', type: 'string', length: 128, unique: true)]
        private readonly string $catalogReference,
    ) {
        if ('' === trim($this->id)) {
            throw new \InvalidArgumentException('Stock item id must not be empty.');
        }

        if ('' === trim($this->catalogReference)) {
            throw new \InvalidArgumentException('Catalog reference must not be empty.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the opaque identity owned by Cataloging rather than duplicating product truth.
     */
    public function catalogReference(): string
    {
        return $this->catalogReference;
    }
}

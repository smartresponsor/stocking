<?php

declare(strict_types=1);

namespace App\Stocking\Entity;

/**
 * Identifies one inventory-managed item while keeping catalog identity external.
 */
final class StockItemEntity
{
    public function __construct(
        private readonly string $id,
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

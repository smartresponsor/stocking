<?php

declare(strict_types=1);

namespace App\Stocking\Entity\Stock;

use Doctrine\ORM\Mapping as ORM;

/**
 * Provides the canonical repository-owned persistence identity for Stocking.
 *
 * Inventory item, quantity, reservation, and movement payload remains in the
 * dedicated Stocking entities rather than accumulating on this composition anchor.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock')]
final class StockEntity
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        private readonly string $id,
    ) {
        if ('' === trim($this->id)) {
            throw new \InvalidArgumentException('Stock id must not be empty.');
        }
    }

    /**
     * Returns the repository-owned Stock identity.
     */
    public function id(): string
    {
        return $this->id;
    }
}

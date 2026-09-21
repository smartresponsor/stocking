<?php

declare(strict_types=1);

namespace App\Stocking\Entity;

use App\Objecting\EntityInterface\ObjectVersionedInterface;
use App\Objecting\EntityTrait\Embeddable\ObjectVersionEmbeddableTrait;
use Doctrine\ORM\Mapping as ORM;

/**
 * Holds quantity facts for one stock item at one externally-owned location.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock_level')]
final class StockLevelEntity implements ObjectVersionedInterface
{
    use ObjectVersionEmbeddableTrait;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'stock_item_id', type: 'string', length: 64)]
        private readonly string $stockItemId,
        #[ORM\Id]
        #[ORM\Column(name: 'location_reference', type: 'string', length: 128)]
        private readonly string $locationReference,
        #[ORM\Column(name: 'on_hand', type: 'integer')]
        private int $onHand = 0,
        #[ORM\Column(type: 'integer')]
        private int $reserved = 0,
        #[ORM\Column(type: 'integer')]
        private int $incoming = 0,
    ) {
        if ('' === trim($this->stockItemId)) {
            throw new \InvalidArgumentException('Stock item id must not be empty.');
        }
        if ('' === trim($this->locationReference)) {
            throw new \InvalidArgumentException('Location reference must not be empty.');
        }
        $this->initializeObjectVersion();
        $this->assertQuantities();
    }

    public function stockItemId(): string
    {
        return $this->stockItemId;
    }

    /**
     * Returns the opaque location identity owned by Locating rather than local location truth.
     */
    public function locationReference(): string
    {
        return $this->locationReference;
    }

    /** Returns physically present quantity at this location. */
    public function onHand(): int
    {
        return $this->onHand;
    }

    /** Returns quantity currently held by reservations. */
    public function reserved(): int
    {
        return $this->reserved;
    }

    /** Returns quantity expected but not yet received. */
    public function incoming(): int
    {
        return $this->incoming;
    }

    /** Returns immediately reservable quantity without counting incoming stock. */
    public function available(): int
    {
        return $this->onHand - $this->reserved;
    }

    /** Adds physically received stock to on-hand quantity. */
    public function receive(int $quantity): void
    {
        $this->assertPositive($quantity);
        $this->onHand += $quantity;
    }

    /** Records expected replenishment without making it immediately available. */
    public function scheduleIncoming(int $quantity): void
    {
        $this->assertPositive($quantity);
        $this->incoming += $quantity;
    }

    /** Converts previously scheduled incoming quantity into on-hand stock. */
    public function receiveIncoming(int $quantity): void
    {
        $this->assertPositive($quantity);
        if ($quantity > $this->incoming) {
            throw new \InvalidArgumentException('Received incoming quantity exceeds the scheduled quantity.');
        }
        $this->incoming -= $quantity;
        $this->onHand += $quantity;
    }

    /** Moves immediately available quantity into the reserved bucket. */
    public function reserve(int $quantity): void
    {
        $this->assertPositive($quantity);
        if ($quantity > $this->available()) {
            throw new \InvalidArgumentException('Reservation exceeds available stock.');
        }
        $this->reserved += $quantity;
    }

    /** Releases reserved quantity back to immediately available stock. */
    public function release(int $quantity): void
    {
        $this->assertPositive($quantity);
        if ($quantity > $this->reserved) {
            throw new \InvalidArgumentException('Release exceeds reserved stock.');
        }
        $this->reserved -= $quantity;
    }

    /** Consumes fulfilled quantity from both reserved and on-hand stock. */
    public function consumeReserved(int $quantity): void
    {
        $this->assertPositive($quantity);
        if ($quantity > $this->reserved) {
            throw new \InvalidArgumentException('Consumption exceeds reserved stock.');
        }
        $this->reserved -= $quantity;
        $this->onHand -= $quantity;
        $this->assertQuantities();
    }

    /** Reconciles on-hand stock while preserving active reservations. */
    public function adjustOnHand(int $newOnHand): void
    {
        if ($newOnHand < $this->reserved) {
            throw new \InvalidArgumentException('On-hand quantity cannot be lower than reserved quantity.');
        }
        $this->onHand = $newOnHand;
        $this->assertQuantities();
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero.');
        }
    }

    private function assertQuantities(): void
    {
        if ($this->onHand < 0 || $this->reserved < 0 || $this->incoming < 0) {
            throw new \InvalidArgumentException('Stock quantities cannot be negative.');
        }
        if ($this->reserved > $this->onHand) {
            throw new \InvalidArgumentException('Reserved quantity cannot exceed on-hand quantity.');
        }
    }
}

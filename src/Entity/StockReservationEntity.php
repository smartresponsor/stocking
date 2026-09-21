<?php

declare(strict_types=1);

namespace App\Stocking\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Represents one Stocking-owned hold against a location-scoped stock level.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock_reservation')]
final class StockReservationEntity
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CONSUMED = 'consumed';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        private readonly string $id,
        #[ORM\Column(name: 'idempotency_key', type: 'string', length: 128, unique: true)]
        private readonly string $idempotencyKey,
        #[ORM\Column(name: 'stock_item_id', type: 'string', length: 64)]
        private readonly string $stockItemId,
        #[ORM\Column(name: 'location_reference', type: 'string', length: 128)]
        private readonly string $locationReference,
        #[ORM\Column(type: 'integer')]
        private readonly int $quantity,
        #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
        private readonly \DateTimeImmutable $expiresAt,
        #[ORM\Column(type: 'string', length: 16)]
        private string $status = self::STATUS_ACTIVE,
    ) {
        if ('' === trim($this->id)) {
            throw new \InvalidArgumentException('Reservation id must not be empty.');
        }
        if ('' === trim($this->idempotencyKey)) {
            throw new \InvalidArgumentException('Reservation idempotency key must not be empty.');
        }
        if ('' === trim($this->stockItemId)) {
            throw new \InvalidArgumentException('Stock item id must not be empty.');
        }
        if ('' === trim($this->locationReference)) {
            throw new \InvalidArgumentException('Location reference must not be empty.');
        }
        if ($this->quantity <= 0) {
            throw new \InvalidArgumentException('Reservation quantity must be greater than zero.');
        }
        if (!in_array($this->status, self::statuses(), true)) {
            throw new \InvalidArgumentException('Reservation status is invalid.');
        }
    }

    /** Returns Stocking reservation identity. */
    public function id(): string
    {
        return $this->id;
    }

    /** Returns the caller-supplied retry identity that must be unique in durable persistence. */
    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    /** Returns the inventory item referenced by this reservation. */
    public function stockItemId(): string
    {
        return $this->stockItemId;
    }

    /** Returns the opaque Locating-owned location reference. */
    public function locationReference(): string
    {
        return $this->locationReference;
    }

    /** Returns the quantity held by this reservation while it is active. */
    public function quantity(): int
    {
        return $this->quantity;
    }

    /** Returns the time after which an active reservation may expire. */
    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /** Returns the current reservation lifecycle status. */
    public function status(): string
    {
        return $this->status;
    }

    /** Reports whether the reservation currently holds stock. */
    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    /**
     * Releases an active reservation; replay after release is a deterministic no-op.
     */
    public function release(StockLevelEntity $level): bool
    {
        if (self::STATUS_RELEASED === $this->status) {
            return false;
        }
        $this->assertActive();
        $this->assertMatches($level);

        $level->release($this->quantity);
        $this->status = self::STATUS_RELEASED;

        return true;
    }

    /**
     * Expires an active reservation once its deadline has passed; replay is a no-op.
     */
    public function expire(\DateTimeImmutable $now, StockLevelEntity $level): bool
    {
        if (self::STATUS_EXPIRED === $this->status) {
            return false;
        }
        $this->assertActive();
        if ($now < $this->expiresAt) {
            return false;
        }
        $this->assertMatches($level);

        $level->release($this->quantity);
        $this->status = self::STATUS_EXPIRED;

        return true;
    }

    /**
     * Consumes an active reservation; replay after consumption is a deterministic no-op.
     */
    public function consume(StockLevelEntity $level): bool
    {
        if (self::STATUS_CONSUMED === $this->status) {
            return false;
        }
        $this->assertActive();
        $this->assertMatches($level);

        $level->consumeReserved($this->quantity);
        $this->status = self::STATUS_CONSUMED;

        return true;
    }

    /** @return list<string> */
    private static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_RELEASED,
            self::STATUS_EXPIRED,
            self::STATUS_CONSUMED,
        ];
    }

    private function assertActive(): void
    {
        if (!$this->isActive()) {
            throw new \LogicException(sprintf('Reservation is already %s.', $this->status));
        }
    }

    private function assertMatches(StockLevelEntity $level): void
    {
        if ($this->stockItemId !== $level->stockItemId() || $this->locationReference !== $level->locationReference()) {
            throw new \InvalidArgumentException('Reservation and stock level identities do not match.');
        }
    }
}

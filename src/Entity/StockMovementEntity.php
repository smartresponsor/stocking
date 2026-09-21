<?php

declare(strict_types=1);

namespace App\Stocking\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Records one immutable stock mutation fact for audit and replay.
 */
#[ORM\Entity]
#[ORM\Table(name: 'stock_movement')]
final readonly class StockMovementEntity
{
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_INCOMING_SCHEDULED = 'incoming_scheduled';
    public const TYPE_INCOMING_RECEIPT = 'incoming_receipt';
    public const TYPE_RESERVATION = 'reservation';
    public const TYPE_RELEASE = 'release';
    public const TYPE_CONSUMPTION = 'consumption';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_TRANSFER_IN = 'transfer_in';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: 'string', length: 64)]
        public string $id,
        #[ORM\Column(name: 'idempotency_key', type: 'string', length: 128, unique: true)]
        public string $idempotencyKey,
        #[ORM\Column(name: 'stock_item_id', type: 'string', length: 64)]
        public string $stockItemId,
        #[ORM\Column(name: 'location_reference', type: 'string', length: 128)]
        public string $locationReference,
        #[ORM\Column(type: 'string', length: 24)]
        public string $type,
        #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
        public \DateTimeImmutable $occurredAt,
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        public ?string $reference = null,
        #[ORM\Column(name: 'on_hand_delta', type: 'integer')]
        public int $onHandDelta = 0,
        #[ORM\Column(name: 'reserved_delta', type: 'integer')]
        public int $reservedDelta = 0,
        #[ORM\Column(name: 'incoming_delta', type: 'integer')]
        public int $incomingDelta = 0,
    ) {
        if ('' === trim($this->id)) {
            throw new \InvalidArgumentException('Movement id must not be empty.');
        }
        if ('' === trim($this->idempotencyKey)) {
            throw new \InvalidArgumentException('Movement idempotency key must not be empty.');
        }
        if ('' === trim($this->stockItemId) || '' === trim($this->locationReference)) {
            throw new \InvalidArgumentException('Movement stock and location identities must not be empty.');
        }
        if (0 === $this->onHandDelta && 0 === $this->reservedDelta && 0 === $this->incomingDelta) {
            throw new \InvalidArgumentException('Movement must change at least one stock quantity bucket.');
        }
        if (!in_array($this->type, self::types(), true)) {
            throw new \InvalidArgumentException('Movement type is invalid.');
        }
    }

    /** @return list<string> */
    private static function types(): array
    {
        return [
            self::TYPE_RECEIPT,
            self::TYPE_INCOMING_SCHEDULED,
            self::TYPE_INCOMING_RECEIPT,
            self::TYPE_RESERVATION,
            self::TYPE_RELEASE,
            self::TYPE_CONSUMPTION,
            self::TYPE_ADJUSTMENT,
            self::TYPE_TRANSFER_OUT,
            self::TYPE_TRANSFER_IN,
        ];
    }
}

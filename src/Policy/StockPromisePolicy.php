<?php

declare(strict_types=1);

namespace App\Stocking\Policy;

/**
 * Defines how far Stocking may promise beyond immediately available physical stock.
 */
final readonly class StockPromisePolicy
{
    public function __construct(
        public bool $includeIncoming = false,
        public int $backorderLimit = 0,
    ) {
        if ($this->backorderLimit < 0) {
            throw new \InvalidArgumentException('Backorder limit cannot be negative.');
        }
    }

    /** Returns a strict policy that promises only immediately available stock. */
    public static function strict(): self
    {
        return new self();
    }

    /** Returns a policy that may promise known incoming quantity. */
    public static function withIncoming(): self
    {
        return new self(includeIncoming: true);
    }

    /** Returns a policy that may promise beyond stock up to an explicit backorder limit. */
    public static function withBackorderLimit(int $backorderLimit, bool $includeIncoming = false): self
    {
        return new self(
            includeIncoming: $includeIncoming,
            backorderLimit: $backorderLimit,
        );
    }
}

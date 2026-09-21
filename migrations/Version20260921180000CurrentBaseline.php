<?php

declare(strict_types=1);

namespace App\Stocking\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Materializes the current Stocking-owned persistence baseline.
 */
final class Version20260921180000CurrentBaseline extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Materialize Stocking inventory, reservation, and movement tables.';
    }

    public function up(Schema $schema): void
    {
        $tables = ['stock_item', 'stock_level', 'stock_reservation', 'stock_movement'];
        $existing = array_values(array_filter(
            $tables,
            static fn (string $table): bool => $schema->hasTable($table),
        ));

        if (count($existing) === count($tables)) {
            return;
        }

        $this->abortIf(
            [] !== $existing,
            sprintf(
                'Partial Stocking baseline detected (%d/%d tables); refusing mixed create/adopt execution.',
                count($existing),
                count($tables),
            ),
        );

        $this->addSql('CREATE TABLE stock_item (id VARCHAR(64) NOT NULL, catalog_reference VARCHAR(128) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_item_catalog_reference ON stock_item (catalog_reference)');

        $this->addSql('CREATE TABLE stock_level (stock_item_id VARCHAR(64) NOT NULL, location_reference VARCHAR(128) NOT NULL, on_hand INT NOT NULL, reserved INT NOT NULL, incoming INT NOT NULL, version INT NOT NULL, etag VARCHAR(128) DEFAULT NULL, PRIMARY KEY (stock_item_id, location_reference))');

        $this->addSql('CREATE TABLE stock_reservation (id VARCHAR(64) NOT NULL, idempotency_key VARCHAR(128) NOT NULL, stock_item_id VARCHAR(64) NOT NULL, location_reference VARCHAR(128) NOT NULL, quantity INT NOT NULL, expires_at TIMESTAMP NOT NULL, status VARCHAR(16) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_reservation_idempotency ON stock_reservation (idempotency_key)');
        $this->addSql('CREATE INDEX idx_stock_reservation_level_status ON stock_reservation (stock_item_id, location_reference, status)');

        $this->addSql('CREATE TABLE stock_movement (id VARCHAR(64) NOT NULL, idempotency_key VARCHAR(128) NOT NULL, stock_item_id VARCHAR(64) NOT NULL, location_reference VARCHAR(128) NOT NULL, type VARCHAR(24) NOT NULL, occurred_at TIMESTAMP NOT NULL, reference VARCHAR(128) DEFAULT NULL, on_hand_delta INT NOT NULL, reserved_delta INT NOT NULL, incoming_delta INT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_movement_idempotency ON stock_movement (idempotency_key)');
        $this->addSql('CREATE INDEX idx_stock_movement_level_time ON stock_movement (stock_item_id, location_reference, occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Current Stocking ORM baseline is the forward schema contract.');
    }
}

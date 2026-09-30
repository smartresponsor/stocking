<?php

declare(strict_types=1);

namespace App\Stocking\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the canonical repository-owned Stock root persistence identity.
 */
final class Version20260930212500RepositoryRootEntity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Stocking repository root stock table.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('stock')) {
            return;
        }

        $this->addSql('CREATE TABLE stock (id VARCHAR(64) NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The Stock root identity is part of the current Stocking persistence contract.');
    }
}

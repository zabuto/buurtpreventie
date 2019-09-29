<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RoundWalker reminded datetime
 */
final class Version20190928134659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reminded datetime for round walker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE round_walker ADD reminded DATETIME DEFAULT NULL AFTER deleted_by_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE round_walker DROP reminded');
    }
}

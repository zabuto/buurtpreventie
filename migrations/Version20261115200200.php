<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115200200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Settings: the settings of the association';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE setting (name VARCHAR(100) NOT NULL, value LONGTEXT NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE setting');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

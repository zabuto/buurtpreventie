<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: every API token expires, at the latest 365 days after it was created';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE api_token SET expires_at = DATE_ADD(created_at, INTERVAL 365 DAY) WHERE expires_at IS NULL');
        $this->addSql('ALTER TABLE api_token CHANGE expires_at expires_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_token CHANGE expires_at expires_at DATETIME DEFAULT NULL');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

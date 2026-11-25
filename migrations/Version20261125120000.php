<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Membership: an admin lets a member use MCP with every role; off for every existing member';
    }

    /**
     * The default fills the existing rows only: the mapping has none, so the column drops it again to match.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member ADD mcp_allowed TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE member ALTER mcp_allowed DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP mcp_allowed');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

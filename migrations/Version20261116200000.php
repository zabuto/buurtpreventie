<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: a chain of hashes over the entries, with its head and anchor; existing entries get a position before the chain';
    }

    /**
     * Existing entries are numbered by time and id and stay outside the chain, without a hash: the anchor and the
     * head take the last of them, so the first new entry links to the start hash.
     */
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_chain (name VARCHAR(10) NOT NULL, chain_position BIGINT NOT NULL, chain_hash BINARY(32) NOT NULL, PRIMARY KEY (name)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE audit_entry ADD chain_position BIGINT DEFAULT NULL, ADD changes_hash BINARY(32) DEFAULT NULL, ADD chain_hash BINARY(32) DEFAULT NULL');
        $this->addSql('UPDATE audit_entry e JOIN (SELECT id, ROW_NUMBER() OVER (ORDER BY occurred_at, id) AS position FROM audit_entry) numbered ON numbered.id = e.id SET e.chain_position = numbered.position');
        $this->addSql('ALTER TABLE audit_entry MODIFY chain_position BIGINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX audit_entry_chain_position ON audit_entry (chain_position)');
        $this->addSql("INSERT INTO audit_chain (name, chain_position, chain_hash) SELECT 'anchor', COALESCE(MAX(chain_position), 0), UNHEX(REPEAT('00', 32)) FROM audit_entry");
        $this->addSql("INSERT INTO audit_chain (name, chain_position, chain_hash) SELECT 'head', COALESCE(MAX(chain_position), 0), UNHEX(REPEAT('00', 32)) FROM audit_entry");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX audit_entry_chain_position ON audit_entry');
        $this->addSql('ALTER TABLE audit_entry DROP chain_position, DROP changes_hash, DROP chain_hash');
        $this->addSql('DROP TABLE audit_chain');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

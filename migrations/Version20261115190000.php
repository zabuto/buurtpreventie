<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: the audit log';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_entry (id BINARY(16) NOT NULL, changes LONGTEXT DEFAULT NULL, imported_from_v3 TINYINT NOT NULL, occurred_at DATETIME NOT NULL, actor_id BINARY(16) DEFAULT NULL, actor_name VARCHAR(100) DEFAULT NULL, action VARCHAR(60) NOT NULL, subject_type VARCHAR(30) NOT NULL, subject_id BINARY(16) NOT NULL, channel VARCHAR(10) NOT NULL, INDEX audit_entry_actor (actor_id), INDEX audit_entry_subject (subject_id), INDEX audit_entry_occurred_at (occurred_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_entry');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

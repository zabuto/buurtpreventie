<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Membership: members';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE member (id BINARY(16) NOT NULL, email VARCHAR(180) DEFAULT NULL COLLATE `utf8mb4_bin`, name VARCHAR(100) DEFAULT NULL, name_key VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_bin`, display_name VARCHAR(100) DEFAULT NULL, display_name_key VARCHAR(100) DEFAULT NULL COLLATE `utf8mb4_bin`, phone VARCHAR(30) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, roles LONGTEXT DEFAULT NULL, consent_contact_share TINYINT NOT NULL, certified TINYINT NOT NULL, active TINYINT NOT NULL, notify_open_rounds TINYINT NOT NULL, erased_at DATETIME DEFAULT NULL, INDEX member_name_key (name_key), UNIQUE INDEX member_email (email), UNIQUE INDEX member_display_name_key (display_name_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

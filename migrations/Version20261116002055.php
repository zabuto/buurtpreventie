<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116002055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: login codes, invitations and the moment of the last new password';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE invitation (id BINARY(16) NOT NULL, secret_hash BINARY(32) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, member_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, INDEX invitation_member (member_id), UNIQUE INDEX invitation_secret_hash (secret_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE login_challenge (expires_at DATETIME NOT NULL, attempts SMALLINT NOT NULL, used_at DATETIME DEFAULT NULL, id CHAR(32) NOT NULL, member_id BINARY(16) DEFAULT NULL, code_hash BINARY(32) NOT NULL, created_at DATETIME NOT NULL, INDEX login_challenge_member (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql('ALTER TABLE account ADD credentials_changed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invitation');
        $this->addSql('DROP TABLE login_challenge');
        $this->addSql('ALTER TABLE account DROP credentials_changed_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115200100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: accounts and API tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE account (id BINARY(16) NOT NULL, password_hash VARCHAR(255) DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, member_id BINARY(16) NOT NULL, UNIQUE INDEX account_member (member_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE api_token (id BINARY(16) NOT NULL, secret_hash BINARY(32) NOT NULL, scopes LONGTEXT NOT NULL, last_used_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, name VARCHAR(100) NOT NULL, expires_at DATETIME DEFAULT NULL, member_id BINARY(16) NOT NULL, created_at DATETIME NOT NULL, INDEX api_token_member (member_id), UNIQUE INDEX api_token_secret_hash (secret_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE api_token');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

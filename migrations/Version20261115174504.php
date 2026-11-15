<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115174504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Patrol: rounds, participations, round reports, outcomes, meeting points and comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE meeting_point (id BINARY(16) NOT NULL, name VARCHAR(100) NOT NULL, name_key VARCHAR(100) NOT NULL COLLATE `utf8mb4_bin`, latitude NUMERIC(9, 6) DEFAULT NULL, longitude NUMERIC(9, 6) DEFAULT NULL, archived TINYINT NOT NULL, UNIQUE INDEX meeting_point_name_key (name_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE outcome (id BINARY(16) NOT NULL, name VARCHAR(100) NOT NULL, name_key VARCHAR(100) NOT NULL COLLATE `utf8mb4_bin`, archived TINYINT NOT NULL, kind VARCHAR(20) NOT NULL, UNIQUE INDEX outcome_name_key (name_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE participation (id BINARY(16) NOT NULL, reminded_at DATETIME DEFAULT NULL, member_id BINARY(16) NOT NULL, joined_at DATETIME NOT NULL, on_behalf_of BINARY(16) DEFAULT NULL, round_id BINARY(16) NOT NULL, INDEX participation_member (member_id), INDEX participation_on_behalf_of (on_behalf_of), UNIQUE INDEX participation_round_member (round_id, member_id), INDEX IDX_AB55E24FA6005CA0 (round_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE round (id BINARY(16) NOT NULL, starts_at DATETIME NOT NULL, start_note LONGTEXT DEFAULT NULL, buddy_call_sent_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, cancel_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, minimum_walkers SMALLINT UNSIGNED NOT NULL, maximum_walkers SMALLINT UNSIGNED NOT NULL, meeting_point_id BINARY(16) DEFAULT NULL, INDEX round_starts_at (starts_at), INDEX IDX_C5EEEA3453B9E377 (meeting_point_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE round_comment (id BINARY(16) NOT NULL, author_id BINARY(16) NOT NULL, text LONGTEXT NOT NULL, posted_at DATETIME NOT NULL, round_id BINARY(16) NOT NULL, INDEX round_comment_author (author_id), INDEX IDX_4BD00A96A6005CA0 (round_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql(
            'CREATE TABLE round_report (id BINARY(16) NOT NULL, note LONGTEXT DEFAULT NULL, author_id BINARY(16) NOT NULL, reported_at DATETIME NOT NULL, outcome_id BINARY(16) NOT NULL, round_id BINARY(16) NOT NULL, INDEX round_report_author (author_id), UNIQUE INDEX round_report_round (round_id), INDEX IDX_984E28E6EE6D63 (outcome_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`',
        );
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FA6005CA0 FOREIGN KEY (round_id) REFERENCES round (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE round ADD CONSTRAINT FK_C5EEEA3453B9E377 FOREIGN KEY (meeting_point_id) REFERENCES meeting_point (id)');
        $this->addSql('ALTER TABLE round_comment ADD CONSTRAINT FK_4BD00A96A6005CA0 FOREIGN KEY (round_id) REFERENCES round (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE round_report ADD CONSTRAINT FK_984E28E6EE6D63 FOREIGN KEY (outcome_id) REFERENCES outcome (id)');
        $this->addSql('ALTER TABLE round_report ADD CONSTRAINT FK_984E28A6005CA0 FOREIGN KEY (round_id) REFERENCES round (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FA6005CA0');
        $this->addSql('ALTER TABLE round DROP FOREIGN KEY FK_C5EEEA3453B9E377');
        $this->addSql('ALTER TABLE round_comment DROP FOREIGN KEY FK_4BD00A96A6005CA0');
        $this->addSql('ALTER TABLE round_report DROP FOREIGN KEY FK_984E28E6EE6D63');
        $this->addSql('ALTER TABLE round_report DROP FOREIGN KEY FK_984E28A6005CA0');
        $this->addSql('DROP TABLE meeting_point');
        $this->addSql('DROP TABLE outcome');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE round');
        $this->addSql('DROP TABLE round_comment');
        $this->addSql('DROP TABLE round_report');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

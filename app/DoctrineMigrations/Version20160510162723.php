<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160510162723 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );
        
        $this->addSql('
            INSERT INTO `zabuto_usergroup` (
                `id` , `name` , `roles`
            ) VALUES (
                NULL , \'Analist\', \'a:1:{i:0;s:12:"ROLE_ANALYST";}\'
            );'
        );
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() != 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );
        
        $this->addSql('
            DELETE FROM `zabuto_usergroup` WHERE `name` = \'Analist\';'
        );
    }
}

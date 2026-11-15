<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115092826 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deal_stage_events (id UUID NOT NULL, stage VARCHAR(50) NOT NULL, entered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, left_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deal_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4DD68398F60E2305 ON deal_stage_events (deal_id)');
        $this->addSql('ALTER TABLE deal_stage_events ADD CONSTRAINT FK_4DD68398F60E2305 FOREIGN KEY (deal_id) REFERENCES deals (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deal_stage_events DROP CONSTRAINT FK_4DD68398F60E2305');
        $this->addSql('DROP TABLE deal_stage_events');
    }
}

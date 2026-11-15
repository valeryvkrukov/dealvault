<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115060803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_alerts (id UUID NOT NULL, alert_type VARCHAR(50) NOT NULL, period VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL, snooze_reason TEXT DEFAULT NULL, snoozed_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, company_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_alert_per_period ON portfolio_alerts (tenant_id, company_id, alert_type, period)');
        $this->addSql('CREATE INDEX IDX_F1912D5E9033212A ON portfolio_alerts (tenant_id)');
        $this->addSql('CREATE INDEX IDX_F1912D5E979B1AD6 ON portfolio_alerts (company_id)');
        $this->addSql('ALTER TABLE portfolio_alerts ADD CONSTRAINT FK_F1912D5E9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_alerts ADD CONSTRAINT FK_F1912D5E979B1AD6 FOREIGN KEY (company_id) REFERENCES portfolio_companies (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_alerts DROP CONSTRAINT FK_F1912D5E9033212A');
        $this->addSql('ALTER TABLE portfolio_alerts DROP CONSTRAINT FK_F1912D5E979B1AD6');
        $this->addSql('DROP TABLE portfolio_alerts');
    }
}

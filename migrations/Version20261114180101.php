<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114180101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_companies (id UUID NOT NULL, name VARCHAR(150) NOT NULL, website VARCHAR(255) DEFAULT NULL, lifecycle_state VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7CEC3B169033212A ON portfolio_companies (tenant_id)');
        $this->addSql('CREATE TABLE portfolio_metric_requirements (id UUID NOT NULL, metric_name VARCHAR(100) NOT NULL, is_required BOOLEAN NOT NULL, company_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EEE3607C979B1AD6 ON portfolio_metric_requirements (company_id)');
        $this->addSql('CREATE TABLE portfolio_reporting_calendars (id UUID NOT NULL, frequency VARCHAR(20) NOT NULL, days_after_period_end INT NOT NULL, company_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_85001B53979B1AD6 ON portfolio_reporting_calendars (company_id)');
        $this->addSql('ALTER TABLE portfolio_companies ADD CONSTRAINT FK_7CEC3B169033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_metric_requirements ADD CONSTRAINT FK_EEE3607C979B1AD6 FOREIGN KEY (company_id) REFERENCES portfolio_companies (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_reporting_calendars ADD CONSTRAINT FK_85001B53979B1AD6 FOREIGN KEY (company_id) REFERENCES portfolio_companies (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_claims ADD CONSTRAINT FK_B848FE8B979B1AD6 FOREIGN KEY (company_id) REFERENCES portfolio_companies (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_B848FE8B979B1AD6 ON portfolio_claims (company_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_companies DROP CONSTRAINT FK_7CEC3B169033212A');
        $this->addSql('ALTER TABLE portfolio_metric_requirements DROP CONSTRAINT FK_EEE3607C979B1AD6');
        $this->addSql('ALTER TABLE portfolio_reporting_calendars DROP CONSTRAINT FK_85001B53979B1AD6');
        $this->addSql('DROP TABLE portfolio_companies');
        $this->addSql('DROP TABLE portfolio_metric_requirements');
        $this->addSql('DROP TABLE portfolio_reporting_calendars');
        $this->addSql('ALTER TABLE portfolio_claims DROP CONSTRAINT FK_B848FE8B979B1AD6');
        $this->addSql('DROP INDEX IDX_B848FE8B979B1AD6');
    }
}

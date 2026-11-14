<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114082644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ingestion_sources (id UUID NOT NULL, type VARCHAR(30) NOT NULL, sha256 VARCHAR(64) DEFAULT NULL, internet_message_id VARCHAR(255) DEFAULT NULL, file_name VARCHAR(255) DEFAULT NULL, storage_path VARCHAR(255) DEFAULT NULL, status VARCHAR(30) NOT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_file_per_tenant ON ingestion_sources (tenant_id, sha256)');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_per_tenant ON ingestion_sources (tenant_id, internet_message_id)');
        $this->addSql('CREATE INDEX IDX_6FAFAEAE9033212A ON ingestion_sources (tenant_id)');
        $this->addSql('CREATE TABLE portfolio_claim_revisions (id UUID NOT NULL, source_locator_excerpt TEXT DEFAULT NULL, extracted_value NUMERIC(15, 2) NOT NULL, capture_method VARCHAR(30) NOT NULL, reviewed_by VARCHAR(180) DEFAULT NULL, review_status VARCHAR(30) NOT NULL, previous_revision_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, claim_id UUID NOT NULL, source_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_534CF3377096A49F ON portfolio_claim_revisions (claim_id)');
        $this->addSql('CREATE INDEX IDX_534CF337953C1C61 ON portfolio_claim_revisions (source_id)');
        $this->addSql('CREATE TABLE portfolio_claims (id UUID NOT NULL, company_id UUID NOT NULL, metric_name VARCHAR(100) NOT NULL, period VARCHAR(20) NOT NULL, currency VARCHAR(3) NOT NULL, active_revision_id UUID DEFAULT NULL, status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B848FE8B9033212A ON portfolio_claims (tenant_id)');
        $this->addSql('ALTER TABLE ingestion_sources ADD CONSTRAINT FK_6FAFAEAE9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_claim_revisions ADD CONSTRAINT FK_534CF3377096A49F FOREIGN KEY (claim_id) REFERENCES portfolio_claims (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_claim_revisions ADD CONSTRAINT FK_534CF337953C1C61 FOREIGN KEY (source_id) REFERENCES ingestion_sources (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE portfolio_claims ADD CONSTRAINT FK_B848FE8B9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingestion_sources DROP CONSTRAINT FK_6FAFAEAE9033212A');
        $this->addSql('ALTER TABLE portfolio_claim_revisions DROP CONSTRAINT FK_534CF3377096A49F');
        $this->addSql('ALTER TABLE portfolio_claim_revisions DROP CONSTRAINT FK_534CF337953C1C61');
        $this->addSql('ALTER TABLE portfolio_claims DROP CONSTRAINT FK_B848FE8B9033212A');
        $this->addSql('DROP TABLE ingestion_sources');
        $this->addSql('DROP TABLE portfolio_claim_revisions');
        $this->addSql('DROP TABLE portfolio_claims');
    }
}

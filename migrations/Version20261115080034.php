<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115080034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE deal_custom_field_definitions (id UUID NOT NULL, field_key VARCHAR(50) NOT NULL, field_name VARCHAR(100) NOT NULL, field_type VARCHAR(30) NOT NULL, is_retired BOOLEAN NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_field_per_tenant ON deal_custom_field_definitions (tenant_id, field_key)');
        $this->addSql('CREATE INDEX IDX_E28B89DC9033212A ON deal_custom_field_definitions (tenant_id)');
        $this->addSql('CREATE TABLE deals (id UUID NOT NULL, company_name VARCHAR(150) NOT NULL, website VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, amount_sought NUMERIC(15, 2) DEFAULT NULL, currency VARCHAR(3) NOT NULL, custom_values JSONB DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, tenant_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_EF39849B9033212A ON deals (tenant_id)');
        $this->addSql('ALTER TABLE deal_custom_field_definitions ADD CONSTRAINT FK_E28B89DC9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE deals ADD CONSTRAINT FK_EF39849B9033212A FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deal_custom_field_definitions DROP CONSTRAINT FK_E28B89DC9033212A');
        $this->addSql('ALTER TABLE deals DROP CONSTRAINT FK_EF39849B9033212A');
        $this->addSql('DROP TABLE deal_custom_field_definitions');
        $this->addSql('DROP TABLE deals');
    }
}

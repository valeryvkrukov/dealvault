<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115095108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE portfolio_company_contacts (id UUID NOT NULL, full_name VARCHAR(150) NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(50) NOT NULL, is_primary BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, company_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_contact_email_per_company ON portfolio_company_contacts (company_id, email)');
        $this->addSql('CREATE INDEX IDX_D31E23D3979B1AD6 ON portfolio_company_contacts (company_id)');
        $this->addSql('ALTER TABLE portfolio_company_contacts ADD CONSTRAINT FK_D31E23D3979B1AD6 FOREIGN KEY (company_id) REFERENCES portfolio_companies (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE portfolio_company_contacts DROP CONSTRAINT FK_D31E23D3979B1AD6');
        $this->addSql('DROP TABLE portfolio_company_contacts');
    }
}

<?php

declare(strict_types=1);

namespace Pulse\FlagsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pulse_feature_flags table for PulseFlags Bundle';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'postgresql') {
            $this->addSql('
                CREATE TABLE IF NOT EXISTS pulse_feature_flags (
                    name VARCHAR(255) PRIMARY KEY,
                    config JSONB NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ');
            $this->addSql('CREATE INDEX idx_updated_at ON pulse_feature_flags (updated_at)');
            $this->addSql('COMMENT ON TABLE pulse_feature_flags IS \'PulseFlags feature flags storage\'');
        } elseif ($platform === 'sqlite') {
            $this->addSql('
                CREATE TABLE IF NOT EXISTS pulse_feature_flags (
                    name VARCHAR(255) PRIMARY KEY,
                    config TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ');
            $this->addSql('CREATE INDEX idx_updated_at ON pulse_feature_flags (updated_at)');
        } else { // MySQL/MariaDB
            $this->addSql('
                CREATE TABLE IF NOT EXISTS pulse_feature_flags (
                    name VARCHAR(255) PRIMARY KEY,
                    config JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_updated_at (updated_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT=\'PulseFlags feature flags storage\'
            ');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS pulse_feature_flags');
    }
}

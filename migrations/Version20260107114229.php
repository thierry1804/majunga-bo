<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260107114229 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_settings table for managing site parameters';
    }

    public function up(Schema $schema): void
    {
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_settings'"
        );

        if ($tables === []) {
            $this->addSql('CREATE TABLE site_settings (
                id CHAR(36) NOT NULL,
                setting_key VARCHAR(255) NOT NULL,
                value TEXT DEFAULT NULL,
                value_type VARCHAR(20) NOT NULL DEFAULT \'string\',
                description TEXT DEFAULT NULL,
                category VARCHAR(100) NOT NULL DEFAULT \'general\',
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY UNIQ_site_settings_key (setting_key)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }

        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $this->addSql("INSERT INTO site_settings (id, setting_key, value, value_type, description, category, is_public)
            SELECT '{$uuid}', 'maintenance_mode', 'false', 'boolean', 'Active ou désactive le mode maintenance du site', 'system', 1
            WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'maintenance_mode')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_settings');
    }
}


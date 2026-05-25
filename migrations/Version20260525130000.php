<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed default public site settings for booking';
    }

    public function up(Schema $schema): void
    {
        $rows = [
            ['booking_enabled', 'true', 'boolean', 'Active ou désactive les réservations en ligne', 'booking', 1],
            ['currency', 'EUR', 'string', 'Devise affichée sur le site', 'booking', 1],
            ['max_booking_participants', '20', 'number', 'Nombre maximum de participants par réservation', 'booking', 1],
            ['admin_notification_email', '', 'string', 'Email admin pour les alertes de réservation', 'system', 0],
            ['maintenance_message', 'Le site est en maintenance. Merci de revenir plus tard.', 'string', 'Message affiché en mode maintenance', 'system', 1],
        ];

        foreach ($rows as [$key, $value, $type, $description, $category, $isPublic]) {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $this->addSql("INSERT INTO site_settings (id, setting_key, value, value_type, description, category, is_public)
                SELECT '$uuid', '$key', '$value', '$type', '$description', '$category', $isPublic
                WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = '$key')");
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM site_settings WHERE setting_key IN ('booking_enabled', 'currency', 'max_booking_participants', 'admin_notification_email', 'maintenance_message')");
    }
}

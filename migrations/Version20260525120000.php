<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enrich shuttle schedules, extend bookings, add password_reset_tokens';
    }

    public function up(Schema $schema): void
    {
        $shuttleColumns = $this->connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shuttle_schedules'"
        );
        $bookingColumns = $this->connection->fetchFirstColumn(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'"
        );
        $tables = $this->connection->fetchFirstColumn(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'password_reset_tokens'"
        );

        if (!in_array('available_seats', $shuttleColumns, true)) {
            $this->addSql('ALTER TABLE shuttle_schedules ADD available_seats INT DEFAULT 20 NOT NULL');
        }
        if (!in_array('shuttle_from', $shuttleColumns, true)) {
            $this->addSql('ALTER TABLE shuttle_schedules ADD shuttle_from VARCHAR(255) DEFAULT NULL');
        }
        if (!in_array('shuttle_to', $shuttleColumns, true)) {
            $this->addSql('ALTER TABLE shuttle_schedules ADD shuttle_to VARCHAR(255) DEFAULT NULL');
        }

        if (!in_array('service_type', $bookingColumns, true)) {
            $this->addSql("ALTER TABLE bookings ADD service_type VARCHAR(20) DEFAULT 'tour' NOT NULL");
        }
        if (!in_array('phone', $bookingColumns, true)) {
            $this->addSql('ALTER TABLE bookings ADD phone TEXT DEFAULT NULL');
        }
        if (!in_array('special_requests', $bookingColumns, true)) {
            $this->addSql('ALTER TABLE bookings ADD special_requests TEXT DEFAULT NULL');
        }
        if (!in_array('shuttle_schedule_id', $bookingColumns, true)) {
            $this->addSql('ALTER TABLE bookings ADD shuttle_schedule_id CHAR(36) DEFAULT NULL');
            $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_bookings_shuttle_schedule FOREIGN KEY (shuttle_schedule_id) REFERENCES shuttle_schedules (id) ON DELETE SET NULL');
        }

        if ($tables === []) {
            $this->addSql('CREATE TABLE password_reset_tokens (
                id CHAR(36) NOT NULL,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )');
            $this->addSql('CREATE INDEX IDX_password_reset_user ON password_reset_tokens (user_id)');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_password_reset_hash ON password_reset_tokens (token_hash)');
            $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_password_reset_user FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP CONSTRAINT FK_password_reset_user');
        $this->addSql('DROP TABLE password_reset_tokens');

        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT FK_bookings_shuttle_schedule');
        $this->addSql('ALTER TABLE bookings DROP shuttle_schedule_id');
        $this->addSql('ALTER TABLE bookings DROP special_requests');
        $this->addSql('ALTER TABLE bookings DROP phone');
        $this->addSql('ALTER TABLE bookings DROP service_type');

        $this->addSql('ALTER TABLE shuttle_schedules DROP shuttle_to');
        $this->addSql('ALTER TABLE shuttle_schedules DROP shuttle_from');
        $this->addSql('ALTER TABLE shuttle_schedules DROP available_seats');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106075421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert image_url to image_urls (JSON array) to support multiple images per tour';
    }

    public function up(Schema $schema): void
    {
        // Créer une nouvelle colonne image_urls de type JSON
        $this->addSql('ALTER TABLE tours ADD COLUMN image_urls JSON DEFAULT NULL');

        // Migrer les données existantes : convertir les valeurs non-null de image_url en tableau JSON
        $this->addSql("
            UPDATE tours 
            SET image_urls = CASE 
                WHEN image_url IS NOT NULL AND image_url != '' 
                THEN json_build_array(image_url)::json
                ELSE '[]'::json
            END
        ");

        // Supprimer l'ancienne colonne image_url
        $this->addSql('ALTER TABLE tours DROP COLUMN image_url');
    }

    public function down(Schema $schema): void
    {
        // Créer la colonne image_url
        $this->addSql('ALTER TABLE tours ADD COLUMN image_url TEXT DEFAULT NULL');

        // Migrer les données : prendre le premier élément du tableau JSON s'il existe
        $this->addSql("
            UPDATE tours 
            SET image_url = CASE 
                WHEN image_urls IS NOT NULL AND json_array_length(image_urls) > 0 
                THEN image_urls->>0
                ELSE NULL
            END
        ");

        // Supprimer la colonne image_urls
        $this->addSql('ALTER TABLE tours DROP COLUMN image_urls');
    }
}

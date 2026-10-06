<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Correctifs de l'audit de sécurité (2026-10-06).
 *
 * - user.api_key_token_version : version de session enregistrée à la
 *   génération de la clé API ; la clé est refusée si la version change.
 *   NULL pour les clés existantes (acceptées comme avant).
 * - media_object.owner_id : compte qui a envoyé le média (contrôle de
 *   propriété de la photo de profil, nettoyage à la suppression du compte).
 *   NULL pour les médias existants.
 *
 * Colonnes ajoutées uniquement (nullable) : aucune donnée existante n'est
 * modifiée. Sauvegarder la base AVANT d'appliquer (cf. README).
 */
final class Version20261006120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clé API liée à la version de session ; propriétaire des médias';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD api_key_token_version INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media_object ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D431327E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_14D431327E3C61F9 ON media_object (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object DROP FOREIGN KEY FK_14D431327E3C61F9');
        $this->addSql('DROP INDEX IDX_14D431327E3C61F9 ON media_object');
        $this->addSql('ALTER TABLE media_object DROP owner_id');
        $this->addSql('ALTER TABLE user DROP api_key_token_version');
    }
}

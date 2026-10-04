<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sécurité des sessions et de la double authentification (audit 2026-10-04).
 *
 * - user_two_factor.pending_secret : secret en cours d'enrôlement, distinct du
 *   secret actif (un /setup ne désactive plus la 2FA existante) ;
 * - user_two_factor.last_used_timestep : anti-rejeu des codes TOTP ;
 * - user.token_version : révocation des JWT (changement de mot de passe, de
 *   rôle, activation de la 2FA, incident).
 *
 * Colonnes ajoutées uniquement (nullable ou avec valeur par défaut) : aucune
 * donnée existante n'est modifiée. Sauvegarder la base AVANT d'appliquer
 * (mysqldump cohérent, cf. README) ; le down() supprime les trois colonnes.
 */
final class Version20261004120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '2FA : secret en attente et anti-rejeu ; révocation des JWT par version';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_two_factor ADD pending_secret VARCHAR(255) DEFAULT NULL, ADD last_used_timestep INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD token_version INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_two_factor DROP pending_secret, DROP last_used_timestep');
        $this->addSql('ALTER TABLE user DROP token_version');
    }
}

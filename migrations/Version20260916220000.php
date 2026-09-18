<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Jeton de réinitialisation de mot de passe.
 *
 * La colonne reçoit l'empreinte SHA-256 du jeton — 64 caractères — et jamais
 * le jeton lui-même : une fuite de la base ne doit pas permettre de prendre
 * les comptes.
 */
final class Version20260916220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le jeton de réinitialisation de mot de passe sur les comptes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateur ADD jeton_reinitialisation VARCHAR(64) DEFAULT NULL, ADD jeton_expiration DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_utilisateur_jeton ON utilisateur (jeton_reinitialisation)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_utilisateur_jeton ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur DROP jeton_reinitialisation, DROP jeton_expiration');
    }
}

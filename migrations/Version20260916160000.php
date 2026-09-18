<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suivi du traitement des messages de contact.
 *
 * Le site annonce une réponse sous 48 heures. Sans ces deux colonnes, rien ne
 * distingue un message auquel on a répondu d'un message oublié.
 */
final class Version20260916160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le suivi de traitement sur les messages de contact.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD traite TINYINT DEFAULT 0 NOT NULL, ADD date_traitement DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP traite, DROP date_traitement');
    }
}

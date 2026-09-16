<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Période de disponibilité des menus.
 *
 * Les deux colonnes sont facultatives : un menu sans date est proposé toute
 * l'année. Elles permettent de distinguer « hors saison » de « épuisé », deux
 * situations que le seul champ `stock` ne savait pas différencier.
 */
final class Version20260916090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une période de disponibilité (date_debut, date_fin) sur les menus.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu ADD date_debut DATE DEFAULT NULL, ADD date_fin DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu DROP date_debut, DROP date_fin');
    }
}

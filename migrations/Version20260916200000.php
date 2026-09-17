<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Suivi de la restitution du matériel prêté.
 *
 * Les maquettes annoncent une restitution sous dix jours ouvrés et une
 * indemnité de 600 € à défaut. `pretMateriel` disait seulement qu'il y avait
 * eu prêt : rien ne traçait le retour, ni l'indemnité facturée.
 */
final class Version20260916200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la date de restitution du matériel et l\'indemnité sur les commandes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD date_restitution_materiel DATE DEFAULT NULL, ADD indemnite_materiel NUMERIC(8, 2) DEFAULT \'0.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP date_restitution_materiel, DROP indemnite_materiel');
    }
}

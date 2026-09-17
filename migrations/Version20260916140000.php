<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remise appliquée sur une commande.
 *
 * `prix_total` seul ne permettait pas de justifier un montant : rien n'indiquait
 * si une remise avait joué ni laquelle. Les deux colonnes figent la remise au
 * moment de la commande, pour qu'une facture émise ne change pas si la règle
 * commerciale évolue.
 */
final class Version20260916140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve la remise appliquée sur chaque commande (taux et montant).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commande ADD taux_remise NUMERIC(5, 2) DEFAULT '0.00' NOT NULL, ADD montant_remise NUMERIC(8, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP taux_remise, DROP montant_remise');
    }
}

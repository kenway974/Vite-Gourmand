<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fixe explicitement la collation des tables.
 *
 * Les CREATE TABLE précédents déclarent « DEFAULT CHARACTER SET utf8mb4 »
 * sans COLLATE : les tables héritent donc de la collation de la base, elle-même
 * choisie par qui l'a créée. Sur un hébergeur qui la crée en collation
 * binaire, « Jean@example.fr » et « jean@example.fr » cessent d'être le même
 * identifiant, et l'index unique sur l'e-mail laisse passer deux comptes.
 *
 * utf8mb4_unicode_ci compare sans tenir compte de la casse ni des accents.
 * La recherche du catalogue, elle, ne dépend plus de ce réglage : elle porte
 * sur des colonnes normalisées en PHP.
 */
final class Version20260917100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fixe la collation utf8mb4_unicode_ci sur toutes les tables.';
    }

    public function up(Schema $schema): void
    {
            $this->addSql('ALTER TABLE allergene CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE avis CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE commande CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE contact CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE horaire CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE ingredient CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE ingredient_allergene CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE menu CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE plat CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE plat_ingredient CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE menu_plat CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE regime CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE suivi_commande CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE theme CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE utilisateur CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE messenger_messages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $this->addSql('ALTER TABLE zone_livraison CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        // Pas de retour en arrière : on ne connaît pas la collation d'origine,
        // et la rétablir au hasard casserait l'unicité des identifiants.
        $this->throwIrreversibleMigrationException();
    }
}

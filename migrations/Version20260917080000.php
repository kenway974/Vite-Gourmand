<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Zones de livraison et supplément associé.
 *
 * Les maquettes annoncent la livraison comprise « dans un rayon de Bordeaux
 * intra-muros » et se taisent sur le reste. Les zones deviennent des données :
 * le traiteur décide des communes desservies et de leur supplément, plutôt
 * qu'un barème inventé dans le code.
 *
 * Les commandes existantes reçoivent le code postal de Bordeaux centre et un
 * supplément nul : c'est l'hypothèse la plus proche de ce qui leur a été
 * facturé, la livraison ayant jusqu'ici toujours été comprise.
 */
final class Version20260917080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les zones de livraison et rattache les commandes à un code postal.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE zone_livraison (id INT AUTO_INCREMENT NOT NULL, code_postal VARCHAR(5) NOT NULL, commune VARCHAR(100) NOT NULL, frais NUMERIC(6, 2) DEFAULT \'0.00\' NOT NULL, UNIQUE INDEX uniq_zone_code_postal (code_postal), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE commande ADD code_postal_livraison VARCHAR(5) DEFAULT NULL, ADD frais_livraison NUMERIC(6, 2) DEFAULT \'0.00\' NOT NULL');
        $this->addSql("UPDATE commande SET code_postal_livraison = '33000' WHERE code_postal_livraison IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP code_postal_livraison, DROP frais_livraison');
        $this->addSql('DROP TABLE zone_livraison');
    }
}

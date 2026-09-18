<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Colonne de recherche normalisée sur les menus.
 *
 * La recherche du catalogue reposait sur la collation de la base : elle
 * fonctionnait sur MySQL, changeait de comportement selon l'hébergeur, et
 * n'était pas testable sur SQLite. « pate de foi » doit trouver « Pâté de
 * foie » partout, pas seulement là où le serveur veut bien plier les accents.
 *
 * La colonne est remplie par App\Service\Normalisateur, côté PHP, à chaque
 * écriture. Le remplissage des lignes existantes se fait ensuite avec :
 *
 *     php bin/console app:normaliser-menus
 */
final class Version20260917120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne de recherche normalisée sur les menus.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu ADD recherche LONGTEXT NOT NULL');

        // Valeur de départ : le titre et la description tels quels, en
        // minuscules. Les accents sont retirés par la commande ci-dessus, que
        // du SQL portable ne saurait pas faire proprement.
        $this->addSql("UPDATE menu SET recherche = LOWER(CONCAT(titre, ' ', description))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu DROP recherche');
    }
}

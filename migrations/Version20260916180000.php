<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ordre chronologique et jours de fermeture sur les horaires.
 *
 * `jour` est une chaîne : trié alphabétiquement, dimanche ouvrait la semaine.
 * La colonne `ordre` rétablit lundi → dimanche côté base.
 *
 * Un jour de fermeture était jusqu'ici codé « 00:00 – 00:00 », ce que rien ne
 * distinguait d'une saisie oubliée ; il se coche désormais, et les deux heures
 * deviennent facultatives.
 */
final class Version20260916180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l\'ordre des jours et le marqueur de fermeture sur les horaires.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE horaire ADD ordre SMALLINT DEFAULT 0 NOT NULL, ADD ferme TINYINT DEFAULT 0 NOT NULL, CHANGE heure_ouverture heure_ouverture TIME DEFAULT NULL, CHANGE heure_fermeture heure_fermeture TIME DEFAULT NULL');

        $this->addSql("UPDATE horaire SET ordre = CASE jour
            WHEN 'Lundi' THEN 0
            WHEN 'Mardi' THEN 1
            WHEN 'Mercredi' THEN 2
            WHEN 'Jeudi' THEN 3
            WHEN 'Vendredi' THEN 4
            WHEN 'Samedi' THEN 5
            WHEN 'Dimanche' THEN 6
            ELSE 7 END");

        // Reprise des jours codés « 00:00 – 00:00 ».
        $this->addSql('UPDATE horaire SET ferme = 1, heure_ouverture = NULL, heure_fermeture = NULL WHERE heure_ouverture = heure_fermeture');

        $this->addSql('CREATE UNIQUE INDEX uniq_horaire_jour ON horaire (jour)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_horaire_jour ON horaire');
        $this->addSql("UPDATE horaire SET heure_ouverture = '00:00:00', heure_fermeture = '00:00:00' WHERE heure_ouverture IS NULL OR heure_fermeture IS NULL");
        $this->addSql('ALTER TABLE horaire DROP ordre, DROP ferme, CHANGE heure_ouverture heure_ouverture TIME NOT NULL, CHANGE heure_fermeture heure_fermeture TIME NOT NULL');
    }
}

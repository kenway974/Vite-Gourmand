<?php

namespace App\DataFixtures;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Contact;
use App\Entity\Menu;
use App\Entity\SuiviCommande;
use App\Entity\Utilisateur;
use App\Entity\ZoneLivraison;
use App\Service\CalculateurPrix;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration pour « Vite & Gourmand ».
 *
 * Chargement : php bin/console doctrine:fixtures:load
 * (la commande vide la base avant de recharger)
 *
 * Tous les comptes créés ici partagent le même mot de passe, indiqué par
 * la constante MOT_DE_PASSE ci-dessous. C'est acceptable pour un jeu de
 * démonstration en local ; ces comptes n'ont pas vocation à exister en
 * production. Le catalogue (menus, plats, thèmes...) est construit par
 * CatalogueDemoLoader, seule partie de ce jeu de données rejouable sans
 * risque en production — voir ChargerCatalogueDemoCommand.
 */
class AppFixtures extends Fixture
{
    public const MOT_DE_PASSE = 'Motdepasse&33!';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CalculateurPrix $calculateurPrix,
        private readonly CatalogueDemoLoader $catalogue,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        ['menus' => $menus, 'zones' => $zones] = $this->catalogue->charger($manager);

        $utilisateurs = $this->chargerUtilisateurs($manager);
        $commandes = $this->chargerCommandes($manager, $utilisateurs, $menus, $zones);
        $this->chargerAvis($manager, $commandes);
        $this->chargerContacts($manager);

        $manager->flush();
    }

    /** @return array<string, Utilisateur> */
    private function chargerUtilisateurs(ObjectManager $manager): array
    {
        // email, prénom, nom, rôles, téléphone, adresse, actif
        $definitions = [
            'admin' => ['admin@vite-gourmand.fr', 'Kenny', 'Pignolet', ['ROLE_ADMIN'], '05 56 12 34 56', "12 cours de l'Intendance, 33000 Bordeaux", true],
            'employe' => ['employe@vite-gourmand.fr', 'Marie', 'Lasserre', ['ROLE_EMPLOYE'], '05 56 23 45 67', "8 rue Sainte-Catherine, 33000 Bordeaux", true],
            'client1' => ['sophie.brunet@example.fr', 'Sophie', 'Brunet', [], '06 12 34 56 78', "24 rue Notre-Dame, 33000 Bordeaux", true],
            'client2' => ['david.marchand@example.fr', 'David', 'Marchand', [], '06 23 45 67 89', "5 avenue de la Libération, 33700 Mérignac", true],
            'client3' => ['laetitia.fontaine@example.fr', 'Laëtitia', 'Fontaine', [], null, "17 allée des Vignes, 33400 Talence", true],
            'inactif' => ['compte.desactive@example.fr', 'Jean', 'Duviella', [], null, null, false],
        ];

        $utilisateurs = [];

        foreach ($definitions as $cle => [$email, $prenom, $nom, $roles, $gsm, $adresse, $actif]) {
            $utilisateur = new Utilisateur();
            $utilisateur->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles($roles)
                ->setGsm($gsm)
                ->setAdressePostale($adresse)
                ->setActif($actif);

            $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, self::MOT_DE_PASSE));

            $manager->persist($utilisateur);
            $utilisateurs[$cle] = $utilisateur;
        }

        return $utilisateurs;
    }

    /**
     * @param array<string, Utilisateur>   $utilisateurs
     * @param array<string, Menu>          $menus
     * @param array<string, ZoneLivraison> $zones
     *
     * @return array<string, Commande>
     */
    private function chargerCommandes(ObjectManager $manager, array $utilisateurs, array $menus, array $zones): array
    {
        // Le prix n'est plus écrit ici : il est calculé par CalculateurPrix, ce
        // qui garantit que le jeu de données respecte les règles de tarification
        // au lieu de les contredire.
        //
        // Le code postal est extrait du lieu de livraison : il détermine la
        // zone desservie et le supplément éventuel.
        //
        // client, menu, jours écoulés depuis la commande, jours avant prestation,
        // heure, lieu, nb personnes, statut, prêt de matériel
        $definitions = [
            'livree1' => ['client1', 'bistrot-bordelais', -30, -22, '12:00', "24 rue Notre-Dame, 33000 Bordeaux", 10, Commande::LIVREE, true],
            'livree2' => ['client2', 'grand-sud-ouest', -20, -14, '19:30', "5 avenue de la Libération, 33700 Mérignac", 8, Commande::LIVREE, false],
            'livree3' => ['client1', 'brunch-bordelais', -15, -9, '11:30', "24 rue Notre-Dame, 33000 Bordeaux", 6, Commande::LIVREE, true],
            'livree4' => ['client3', 'table-sans-gluten', -12, -5, '12:00', "17 allée des Vignes, 33400 Talence", 6, Commande::LIVREE, false],
            'preparation' => ['client3', 'buffet-anniversaire', -6, 2, '11:00', "17 allée des Vignes, 33400 Talence", 25, Commande::EN_PREPARATION, true],
            'confirmee' => ['client2', 'bassin-arcachon', -3, 6, '19:00', "5 avenue de la Libération, 33700 Mérignac", 12, Commande::CONFIRMEE, false],
            'attente' => ['client3', 'buffet-mariage', -1, 25, '18:00', "Château Pape Clément, 33600 Pessac", 60, Commande::EN_ATTENTE, true],
            'annulee' => ['client1', 'cocktail-girondin', -10, -2, '18:30', "24 rue Notre-Dame, 33000 Bordeaux", 20, Commande::ANNULEE, false],
        ];

        $commandes = [];

        foreach ($definitions as $cle => [$client, $menu, $joursCommande, $joursPrestation, $heure, $lieu, $nbPersonnes, $statut, $materiel]) {
            $commande = new Commande();
            $commande->setUtilisateur($utilisateurs[$client])
                ->setMenu($menus[$menu])
                ->setDateCommande(new \DateTime(sprintf('%+d days', $joursCommande)))
                ->setDatePrestation(new \DateTime(sprintf('%+d days', $joursPrestation)))
                ->setHeureLivraison(new \DateTime($heure))
                ->setLieuLivraison($lieu)
                ->setStatut($statut)
                ->setPretMateriel($materiel);

            preg_match('/\b(\d{5})\b/', $lieu, $trouve);
            $commande->setCodePostalLivraison($trouve[1]);

            // Effectif, total, remise et frais de livraison sont posés d'un
            // seul geste, par le calculateur : le jeu de données ne peut pas
            // contredire les règles de tarification.
            $commande->appliquerPrix($this->calculateurPrix->calculer(
                $menus[$menu],
                $nbPersonnes,
                $zones[$trouve[1]] ?? null,
            ));

            $manager->persist($commande);
            $commandes[$cle] = $commande;

            $this->chargerSuivi($manager, $commande, $statut, $joursCommande);
        }

        // Deux cas de prêt à observer sur la page « Matériel prêté » :
        // livree3 est revenu dans les temps, livree1 a dépassé les dix jours
        // ouvrés sans avoir été rendu.
        $commandes['livree3']->restituerMateriel(new \DateTime('-7 days'));

        return $commandes;
    }

    /**
     * Reconstitue l'historique de statuts cohérent avec l'état actuel.
     */
    private function chargerSuivi(ObjectManager $manager, Commande $commande, string $statutFinal, int $joursCommande): void
    {
        $parcours = match ($statutFinal) {
            Commande::LIVREE => [Commande::EN_ATTENTE, Commande::CONFIRMEE, Commande::EN_PREPARATION, Commande::LIVREE],
            Commande::EN_PREPARATION => [Commande::EN_ATTENTE, Commande::CONFIRMEE, Commande::EN_PREPARATION],
            Commande::CONFIRMEE => [Commande::EN_ATTENTE, Commande::CONFIRMEE],
            Commande::ANNULEE => [Commande::EN_ATTENTE, Commande::CONFIRMEE, Commande::ANNULEE],
            default => [Commande::EN_ATTENTE],
        };

        foreach ($parcours as $index => $statut) {
            $suivi = (new SuiviCommande())
                ->setCommande($commande)
                ->setStatut($statut)
                ->setDateModification(new \DateTime(sprintf('%+d days', $joursCommande + $index)))
                ->setModeContact($index === 0 ? 'site web' : 'email');

            if (Commande::ANNULEE === $statut) {
                $suivi->setMotif("Annulation à la demande du client, plus de 48 h avant la prestation.");
            }

            $manager->persist($suivi);
        }
    }

    /** @param array<string, Commande> $commandes */
    private function chargerAvis(ObjectManager $manager, array $commandes): void
    {
        // Un avis par commande : Commande::$avis est un OneToOne, la base porte
        // une contrainte d'unicité sur avis.commande_id.
        //
        // commande, note, commentaire, statut de validation, jours écoulés
        $definitions = [
            ['livree1', 5, "Entrecôte parfaitement cuite et la sauce bordelaise était à tomber. Livraison pile à l'heure, on recommandera.", Avis::VALIDE, -20],
            ['livree2', 4, "Très bon confit, peau bien croustillante. Un peu juste sur les haricots pour huit personnes.", Avis::VALIDE, -12],
            ['livree3', 5, "Enfin un traiteur qui soigne le végétarien. Le tourin était une vraie surprise, et les canelés impeccables.", Avis::VALIDE, -7],
            ['livree4', 3, "Bon dans l'ensemble, mais les pruneaux à l'armagnac sont arrivés écrasés.", Avis::EN_ATTENTE, -2],
        ];

        foreach ($definitions as [$cleCommande, $note, $commentaire, $statut, $jours]) {
            $commande = $commandes[$cleCommande];

            $avis = (new Avis())
                ->setCommande($commande)
                ->setUtilisateur($commande->getUtilisateur())
                ->setNote($note)
                ->setCommentaire($commentaire)
                ->setStatutValidation($statut)
                ->setDateCreation(new \DateTime(sprintf('%+d days', $jours)));

            $manager->persist($avis);
        }
    }

    private function chargerContacts(ObjectManager $manager): void
    {
        $definitions = [
            ["Devis pour un séminaire", "Bonjour, nous organisons un séminaire pour 80 personnes le mois prochain à Bordeaux. Proposez-vous des formules adaptées ?", 'contact@entreprise-gironde.fr', -5],
            ["Question sur les allergènes", "Ma fille est allergique aux fruits à coque. Le menu Bistrot Bordelais lui conviendrait-il ?", 'famille.robert@example.fr', -3],
            ["Livraison hors agglomération", "Livrez-vous jusqu'au Cap Ferret ? Merci d'avance.", 'vacancier@example.fr', -1],
        ];

        foreach ($definitions as [$titre, $message, $email, $jours]) {
            $contact = (new Contact())
                ->setTitre($titre)
                ->setMessage($message)
                ->setEmail($email)
                ->setDateCreation(new \DateTime(sprintf('%+d days', $jours)));

            // Le plus ancien a reçu sa réponse ; les deux autres sont en attente,
            // dont un qui dépasse déjà les 48 heures annoncées.
            if ($jours <= -5) {
                $contact->marquerTraite();
            }

            $manager->persist($contact);
        }
    }
}

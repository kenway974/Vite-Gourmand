<?php

namespace App\DataFixtures;

use App\Entity\Allergene;
use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Contact;
use App\Entity\Horaire;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\SuiviCommande;
use App\Entity\Theme;
use App\Entity\Utilisateur;
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
 * production.
 */
class AppFixtures extends Fixture
{
    public const MOT_DE_PASSE = 'Motdepasse&974!';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $allergenes = $this->chargerAllergenes($manager);
        $ingredients = $this->chargerIngredients($manager, $allergenes);
        $plats = $this->chargerPlats($manager, $ingredients);
        $themes = $this->chargerThemes($manager);
        $regimes = $this->chargerRegimes($manager);
        $menus = $this->chargerMenus($manager, $themes, $regimes, $plats);
        $utilisateurs = $this->chargerUtilisateurs($manager);

        $this->chargerHoraires($manager);
        $commandes = $this->chargerCommandes($manager, $utilisateurs, $menus);
        $this->chargerAvis($manager, $commandes);
        $this->chargerContacts($manager);

        $manager->flush();
    }

    /**
     * Les 14 allergènes à déclaration obligatoire (règlement UE 1169/2011).
     *
     * @return array<string, Allergene>
     */
    private function chargerAllergenes(ObjectManager $manager): array
    {
        $libelles = [
            'gluten' => 'Gluten',
            'crustaces' => 'Crustacés',
            'oeufs' => 'Œufs',
            'poissons' => 'Poissons',
            'arachides' => 'Arachides',
            'soja' => 'Soja',
            'lait' => 'Lait',
            'fruits-a-coque' => 'Fruits à coque',
            'celeri' => 'Céleri',
            'moutarde' => 'Moutarde',
            'sesame' => 'Graines de sésame',
            'sulfites' => 'Sulfites',
            'lupin' => 'Lupin',
            'mollusques' => 'Mollusques',
        ];

        $allergenes = [];

        foreach ($libelles as $cle => $libelle) {
            $allergene = (new Allergene())->setLibelle($libelle);
            $manager->persist($allergene);
            $allergenes[$cle] = $allergene;
        }

        return $allergenes;
    }

    /**
     * @param array<string, Allergene> $allergenes
     *
     * @return array<string, Ingredient>
     */
    private function chargerIngredients(ObjectManager $manager, array $allergenes): array
    {
        // nom => allergènes contenus
        $definitions = [
            'farine' => ['Farine de blé', ['gluten']],
            'oeuf' => ['Œuf', ['oeufs']],
            'lait' => ['Lait', ['lait']],
            'beurre' => ['Beurre', ['lait']],
            'creme' => ['Crème fraîche', ['lait']],
            'riz' => ['Riz', []],
            'haricots' => ['Haricots rouges', []],
            'tomate' => ['Tomate', []],
            'oignon' => ['Oignon', []],
            'ail' => ['Ail', []],
            'gingembre' => ['Gingembre', []],
            'curcuma' => ['Curcuma', []],
            'piment' => ['Piment', []],
            'thym' => ['Thym', []],
            'poulet' => ['Poulet fermier', []],
            'porc' => ['Échine de porc', []],
            'saucisse' => ['Saucisse fumée', ['sulfites']],
            'crevette' => ['Crevette', ['crustaces']],
            'thon' => ['Thon', ['poissons']],
            'cabillaud' => ['Cabillaud', ['poissons']],
            'sauce-soja' => ['Sauce soja', ['soja', 'gluten']],
            'cacahuete' => ['Cacahuète', ['arachides']],
            'amande' => ['Amande', ['fruits-a-coque']],
            'sesame' => ['Graine de sésame', ['sesame']],
            'moutarde' => ['Moutarde', ['moutarde']],
            'vanille' => ['Vanille Bourbon', []],
            'patate-douce' => ['Patate douce', []],
            'coco' => ['Noix de coco râpée', []],
            'sucre' => ['Sucre de canne', []],
            'palmiste' => ['Cœur de palmiste', []],
        ];

        $ingredients = [];

        foreach ($definitions as $cle => [$nom, $cles]) {
            $ingredient = (new Ingredient())->setNom($nom);

            foreach ($cles as $cleAllergene) {
                $ingredient->addAllergene($allergenes[$cleAllergene]);
            }

            $manager->persist($ingredient);
            $ingredients[$cle] = $ingredient;
        }

        return $ingredients;
    }

    /**
     * @param array<string, Ingredient> $ingredients
     *
     * @return array<string, Plat>
     */
    private function chargerPlats(ObjectManager $manager, array $ingredients): array
    {
        $definitions = [
            'samoussas' => ['Samoussas au thon', 'Entrée', "Petits triangles croustillants garnis de thon relevé au curcuma.", ['farine', 'thon', 'oignon', 'curcuma']],
            'bouchons' => ['Bouchons créoles', 'Entrée', "Bouchons vapeur au porc et au gingembre, servis avec leur sauce.", ['farine', 'porc', 'gingembre', 'sauce-soja']],
            'salade-palmiste' => ['Salade de cœur de palmiste', 'Entrée', "Le « millionnaire » réunionnais, en salade fraîche.", ['palmiste', 'tomate', 'moutarde']],
            'rougail-saucisse' => ['Rougail saucisse', 'Plat', "Le plat emblématique de La Réunion : saucisses fumées mijotées en sauce tomate épicée.", ['saucisse', 'tomate', 'oignon', 'ail', 'piment', 'thym']],
            'cari-poulet' => ['Cari de poulet', 'Plat', "Poulet fermier mijoté au curcuma, gingembre et oignon.", ['poulet', 'curcuma', 'gingembre', 'oignon', 'ail']],
            'civet-porc' => ['Civet de porc', 'Plat', "Échine de porc longuement mijotée, relevée au thym et au piment.", ['porc', 'oignon', 'ail', 'thym', 'piment']],
            'cari-crevettes' => ['Cari de crevettes', 'Plat', "Crevettes saisies puis mijotées en sauce créole.", ['crevette', 'tomate', 'curcuma', 'ail']],
            'massale-cabillaud' => ['Massalé de cabillaud', 'Plat', "Dos de cabillaud au massalé, doux et parfumé.", ['cabillaud', 'curcuma', 'gingembre', 'tomate']],
            'cari-legumes' => ['Cari de légumes', 'Plat', "Version végétarienne du cari, généreuse en légumes de saison.", ['patate-douce', 'tomate', 'oignon', 'curcuma', 'ail']],
            'riz-blanc' => ['Riz blanc', 'Accompagnement', "Riz parfumé, cuisson vapeur.", ['riz']],
            'grains' => ['Grains (haricots rouges)', 'Accompagnement', "Haricots rouges mijotés, l'accompagnement indissociable du cari.", ['haricots', 'oignon', 'thym']],
            'rougail-tomate' => ['Rougail tomate', 'Accompagnement', "Condiment frais et pimenté, à doser selon le courage.", ['tomate', 'oignon', 'piment']],
            'achards' => ['Achards de légumes', 'Accompagnement', "Légumes croquants marinés au curcuma.", ['curcuma', 'ail', 'moutarde']],
            'gateau-patate' => ['Gâteau patate douce', 'Dessert', "Le dessert créole par excellence, moelleux et parfumé à la vanille.", ['patate-douce', 'oeuf', 'sucre', 'vanille', 'beurre']],
            'tarte-vanille' => ['Tarte à la vanille Bourbon', 'Dessert', "Pâte sablée et crème à la vanille de Bourbon Pointu.", ['farine', 'beurre', 'oeuf', 'lait', 'vanille', 'sucre']],
            'salade-fruits' => ['Salade de fruits tropicaux', 'Dessert', "Ananas Victoria, mangue et litchi selon la saison.", ['sucre', 'coco']],
        ];

        $plats = [];

        foreach ($definitions as $cle => [$nom, $type, $description, $cles]) {
            $plat = (new Plat())->setNom($nom)->setType($type)->setDescription($description);

            foreach ($cles as $cleIngredient) {
                $plat->addIngredient($ingredients[$cleIngredient]);
            }

            $manager->persist($plat);
            $plats[$cle] = $plat;
        }

        return $plats;
    }

    /** @return array<string, Theme> */
    private function chargerThemes(ObjectManager $manager): array
    {
        $definitions = [
            'creole' => ['Créole traditionnel', "Les classiques de la cuisine réunionnaise, cuisinés comme à la maison."],
            'festif' => ['Buffet festif', "Formules généreuses pour mariages, anniversaires et grandes tablées."],
            'cocktail' => ['Cocktail dînatoire', "Bouchées et pièces salées à partager debout."],
            'metropole' => ['Cuisine métropolitaine', "Des recettes de l'Hexagone, pour changer du cari."],
            'brunch' => ['Brunch', "Formule du week-end, sucrée et salée."],
        ];

        return $this->chargerLibelles($manager, Theme::class, $definitions);
    }

    /** @return array<string, Regime> */
    private function chargerRegimes(ObjectManager $manager): array
    {
        $definitions = [
            'standard' => ['Standard', "Sans restriction particulière."],
            'vegetarien' => ['Végétarien', "Sans viande ni poisson."],
            'sans-porc' => ['Sans porc', "Aucune préparation à base de porc."],
            'sans-gluten' => ['Sans gluten', "Adapté aux intolérants au gluten."],
        ];

        return $this->chargerLibelles($manager, Regime::class, $definitions);
    }

    /**
     * Theme et Regime partagent la même forme : libellé + description.
     *
     * @param class-string<Theme|Regime>            $classe
     * @param array<string, array{string, string}>  $definitions
     *
     * @return array<string, Theme|Regime>
     */
    private function chargerLibelles(ObjectManager $manager, string $classe, array $definitions): array
    {
        $resultats = [];

        foreach ($definitions as $cle => [$libelle, $description]) {
            $entite = (new $classe())->setLibelle($libelle)->setDescription($description);
            $manager->persist($entite);
            $resultats[$cle] = $entite;
        }

        return $resultats;
    }

    /**
     * @param array<string, Theme>  $themes
     * @param array<string, Regime> $regimes
     * @param array<string, Plat>   $plats
     *
     * @return array<string, Menu>
     */
    private function chargerMenus(ObjectManager $manager, array $themes, array $regimes, array $plats): array
    {
        // titre, thème, régime, min pers., prix, délai, stock, précautions, plats
        $definitions = [
            'creole-decouverte' => [
                'Créole Découverte', 'creole', 'standard', 6, '18.50', 3, 12,
                "Contient du porc. Le rougail tomate est servi à part pour doser le piment.",
                ['samoussas', 'rougail-saucisse', 'riz-blanc', 'grains', 'rougail-tomate', 'gateau-patate'],
            ],
            'creole-prestige' => [
                'Créole Prestige', 'creole', 'standard', 10, '32.00', 5, 6,
                "Contient crustacés, poisson et porc.",
                ['bouchons', 'samoussas', 'cari-crevettes', 'civet-porc', 'riz-blanc', 'grains', 'achards', 'tarte-vanille'],
            ],
            'creole-volaille' => [
                'Cari de Volaille', 'creole', 'sans-porc', 6, '21.00', 3, 15,
                "Aucune préparation à base de porc.",
                ['salade-palmiste', 'cari-poulet', 'riz-blanc', 'grains', 'salade-fruits'],
            ],
            'ocean-indien' => [
                'Océan Indien', 'creole', 'sans-porc', 8, '27.50', 4, 8,
                "Contient poisson et crustacés.",
                ['samoussas', 'massale-cabillaud', 'cari-crevettes', 'riz-blanc', 'achards', 'salade-fruits'],
            ],
            'jardin-creole' => [
                'Jardin Créole', 'creole', 'vegetarien', 6, '16.00', 3, 20,
                "Entièrement végétarien.",
                ['salade-palmiste', 'cari-legumes', 'riz-blanc', 'grains', 'achards', 'salade-fruits'],
            ],
            'buffet-mariage' => [
                'Buffet Mariage', 'festif', 'standard', 40, '38.00', 15, 3,
                "Contient tous les allergènes majeurs. Composition adaptable sur demande.",
                ['bouchons', 'samoussas', 'salade-palmiste', 'rougail-saucisse', 'cari-poulet', 'cari-crevettes', 'riz-blanc', 'grains', 'achards', 'gateau-patate', 'tarte-vanille'],
            ],
            'buffet-anniversaire' => [
                'Buffet Anniversaire', 'festif', 'sans-porc', 20, '29.00', 10, 5,
                "Sans porc. Contient poisson.",
                ['samoussas', 'cari-poulet', 'massale-cabillaud', 'riz-blanc', 'grains', 'gateau-patate'],
            ],
            'cocktail-sale' => [
                'Cocktail Salé', 'cocktail', 'standard', 15, '14.00', 5, 10,
                "Pièces à partager, servies froides ou tièdes.",
                ['bouchons', 'samoussas', 'salade-palmiste'],
            ],
            'brunch-dimanche' => [
                'Brunch du Dimanche', 'brunch', 'vegetarien', 4, '19.50', 2, 10,
                "Végétarien. Contient gluten, lait et œuf.",
                ['salade-palmiste', 'cari-legumes', 'riz-blanc', 'tarte-vanille', 'salade-fruits'],
            ],
            'sans-gluten-creole' => [
                'Créole Sans Gluten', 'creole', 'sans-gluten', 6, '23.00', 4, 7,
                "Élaboré sans ingrédient contenant du gluten.",
                ['cari-poulet', 'riz-blanc', 'grains', 'rougail-tomate', 'salade-fruits'],
            ],
        ];

        $menus = [];

        foreach ($definitions as $cle => [$titre, $theme, $regime, $nbMin, $prix, $delai, $stock, $precautions, $clesPlats]) {
            $menu = new Menu();
            $menu->setTitre($titre)
                ->setDescription($this->descriptionDeMenu($titre, $clesPlats, $plats))
                ->setTheme($themes[$theme])
                ->setRegime($regimes[$regime])
                ->setNbMinPersonnes($nbMin)
                ->setPrixMin($prix)
                ->setDelaiCommandeJours($delai)
                ->setStock($stock)
                ->setPrecautions($precautions);

            foreach ($clesPlats as $clePlat) {
                $menu->addPlat($plats[$clePlat]);
            }

            $manager->persist($menu);
            $menus[$cle] = $menu;
        }

        return $menus;
    }

    /**
     * @param list<string>        $clesPlats
     * @param array<string, Plat> $plats
     */
    private function descriptionDeMenu(string $titre, array $clesPlats, array $plats): string
    {
        $noms = array_map(static fn (string $cle): string => $plats[$cle]->getNom(), $clesPlats);

        return sprintf(
            "Formule « %s », composée de %d préparations : %s.",
            $titre,
            \count($noms),
            implode(', ', $noms)
        );
    }

    /** @return array<string, Utilisateur> */
    private function chargerUtilisateurs(ObjectManager $manager): array
    {
        // email, prénom, nom, rôles, téléphone, adresse, actif
        $definitions = [
            'admin' => ['admin@vite-gourmand.fr', 'Kenny', 'Pignolet', ['ROLE_ADMIN'], '0692 12 34 56', "12 rue de la Compagnie, 97400 Saint-Denis", true],
            'employe' => ['employe@vite-gourmand.fr', 'Marie', 'Hoarau', ['ROLE_EMPLOYE'], '0692 23 45 67', "5 rue Juliette Dodu, 97400 Saint-Denis", true],
            'client1' => ['sophie.grondin@example.fr', 'Sophie', 'Grondin', [], '0692 34 56 78', "8 chemin des Manguiers, 97490 Sainte-Clotilde", true],
            'client2' => ['david.payet@example.fr', 'David', 'Payet', [], '0692 45 67 89', "22 rue du Stade, 97410 Saint-Pierre", true],
            'client3' => ['laetitia.fontaine@example.fr', 'Laëtitia', 'Fontaine', [], null, "3 allée des Filaos, 97434 Saint-Gilles", true],
            'inactif' => ['compte.desactive@example.fr', 'Jean', 'Técher', [], null, null, false],
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

    private function chargerHoraires(ObjectManager $manager): void
    {
        // Le champ `jour` est une chaîne : l'ordre chronologique doit être
        // rétabli à l'affichage, un tri alphabétique donnerait dimanche en tête.
        $semaine = [
            ['Lundi', '09:00', '18:00'],
            ['Mardi', '09:00', '18:00'],
            ['Mercredi', '09:00', '18:00'],
            ['Jeudi', '09:00', '18:00'],
            ['Vendredi', '09:00', '19:00'],
            ['Samedi', '09:00', '13:00'],
            ['Dimanche', '00:00', '00:00'],
        ];

        foreach ($semaine as [$jour, $ouverture, $fermeture]) {
            $horaire = (new Horaire())
                ->setJour($jour)
                ->setHeureOuverture(new \DateTime($ouverture))
                ->setHeureFermeture(new \DateTime($fermeture));

            $manager->persist($horaire);
        }
    }

    /**
     * @param array<string, Utilisateur> $utilisateurs
     * @param array<string, Menu>        $menus
     *
     * @return array<string, Commande>
     */
    private function chargerCommandes(ObjectManager $manager, array $utilisateurs, array $menus): array
    {
        // client, menu, jours écoulés depuis la commande, jours avant prestation,
        // heure, lieu, nb personnes, prix total, statut, prêt de matériel
        $definitions = [
            'livree1' => ['client1', 'creole-decouverte', -30, -22, '12:00', "8 chemin des Manguiers, 97490 Sainte-Clotilde", 10, '185.00', 'livrée', true],
            'livree2' => ['client2', 'creole-volaille', -20, -14, '19:30', "22 rue du Stade, 97410 Saint-Pierre", 8, '168.00', 'livrée', false],
            'livree3' => ['client1', 'jardin-creole', -15, -9, '12:30', "8 chemin des Manguiers, 97490 Sainte-Clotilde", 6, '96.00', 'livrée', false],
            'livree4' => ['client3', 'sans-gluten-creole', -12, -5, '12:00', "3 allée des Filaos, 97434 Saint-Gilles", 6, '138.00', 'livrée', false],
            'preparation' => ['client3', 'buffet-anniversaire', -6, 2, '11:00', "3 allée des Filaos, 97434 Saint-Gilles", 25, '725.00', 'en préparation', true],
            'confirmee' => ['client2', 'ocean-indien', -3, 6, '19:00', "22 rue du Stade, 97410 Saint-Pierre", 12, '330.00', 'confirmée', false],
            'attente' => ['client3', 'buffet-mariage', -1, 25, '18:00', "Domaine du Grand Hazier, 97438 Sainte-Marie", 60, '2280.00', 'en attente', true],
            'annulee' => ['client1', 'cocktail-sale', -10, -2, '18:30', "8 chemin des Manguiers, 97490 Sainte-Clotilde", 20, '280.00', 'annulée', false],
        ];

        $commandes = [];

        foreach ($definitions as $cle => [$client, $menu, $joursCommande, $joursPrestation, $heure, $lieu, $nbPersonnes, $prix, $statut, $materiel]) {
            $commande = new Commande();
            $commande->setUtilisateur($utilisateurs[$client])
                ->setMenu($menus[$menu])
                ->setDateCommande(new \DateTime(sprintf('%+d days', $joursCommande)))
                ->setDatePrestation(new \DateTime(sprintf('%+d days', $joursPrestation)))
                ->setHeureLivraison(new \DateTime($heure))
                ->setLieuLivraison($lieu)
                ->setNbPersonnes($nbPersonnes)
                ->setPrixTotal($prix)
                ->setStatut($statut)
                ->setPretMateriel($materiel);

            $manager->persist($commande);
            $commandes[$cle] = $commande;

            $this->chargerSuivi($manager, $commande, $statut, $joursCommande);
        }

        return $commandes;
    }

    /**
     * Reconstitue l'historique de statuts cohérent avec l'état actuel.
     */
    private function chargerSuivi(ObjectManager $manager, Commande $commande, string $statutFinal, int $joursCommande): void
    {
        $parcours = match ($statutFinal) {
            'livrée' => ['en attente', 'confirmée', 'en préparation', 'livrée'],
            'en préparation' => ['en attente', 'confirmée', 'en préparation'],
            'confirmée' => ['en attente', 'confirmée'],
            'annulée' => ['en attente', 'confirmée', 'annulée'],
            default => ['en attente'],
        };

        foreach ($parcours as $index => $statut) {
            $suivi = (new SuiviCommande())
                ->setCommande($commande)
                ->setStatut($statut)
                ->setDateModification(new \DateTime(sprintf('%+d days', $joursCommande + $index)))
                ->setModeContact($index === 0 ? 'site web' : 'email');

            if ('annulée' === $statut) {
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
            ['livree1', 5, "Rougail excellent, quantités généreuses. Livraison pile à l'heure, on recommandera.", 'validé', -20],
            ['livree2', 4, "Très bon cari, bien parfumé. Un peu juste sur le riz pour huit personnes.", 'validé', -12],
            ['livree3', 5, "Enfin un traiteur qui soigne le végétarien. Le cari de légumes était une vraie réussite.", 'validé', -7],
            ['livree4', 3, "Bon dans l'ensemble, mais le gâteau patate est arrivé écrasé.", 'en attente', -2],
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
            ["Devis pour un séminaire", "Bonjour, nous organisons un séminaire pour 80 personnes le mois prochain. Proposez-vous des formules adaptées ?", 'contact@entreprise-974.fr', -5],
            ["Question sur les allergènes", "Ma fille est allergique aux fruits à coque. Le menu Créole Découverte lui conviendrait-il ?", 'famille.robert@example.fr', -3],
            ["Livraison dans les Hauts", "Livrez-vous jusqu'à Cilaos ? Merci d'avance.", 'randonneur@example.fr', -1],
        ];

        foreach ($definitions as [$titre, $message, $email, $jours]) {
            $contact = (new Contact())
                ->setTitre($titre)
                ->setMessage($message)
                ->setEmail($email)
                ->setDateCreation(new \DateTime(sprintf('%+d days', $jours)));

            $manager->persist($contact);
        }
    }
}

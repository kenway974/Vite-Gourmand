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
use App\Service\CalculateurPrix;
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
    public const MOT_DE_PASSE = 'Motdepasse&33!';

    /**
     * Périodes de disponibilité des menus événementiels.
     *
     * Les menus sans entrée ici sont proposés toute l'année. Hors période, un
     * menu reste visible au catalogue mais annonce « bientôt disponible » :
     * c'est ce qui fait savoir au visiteur qu'on le propose.
     */
    private const PERIODES = [
        'noel-tradition' => ['2026-11-15', '2026-12-24'],
        'noel-marin' => ['2026-11-15', '2026-12-24'],
        'reveillon-prestige' => ['2026-12-01', '2026-12-31'],
        'reveillon-cocktail' => ['2026-12-01', '2026-12-31'],
        'paques-tradition' => ['2027-03-01', '2027-04-05'],
        'paques-vegetal' => ['2027-03-01', '2027-04-05'],
        'valentin-duo' => ['2027-01-20', '2027-02-14'],
        'valentin-marin' => ['2027-01-20', '2027-02-14'],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly CalculateurPrix $calculateurPrix,
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
            'pain' => ['Pain de campagne', ['gluten']],
            'oeuf' => ['Œuf fermier', ['oeufs']],
            'lait' => ['Lait entier', ['lait']],
            'beurre' => ['Beurre doux', ['lait']],
            'creme' => ['Crème fraîche', ['lait']],
            'echalote' => ['Échalote grise', []],
            'ail' => ['Ail rose', []],
            'persil' => ['Persil plat', []],
            'thym' => ['Thym', []],
            'vin-rouge' => ['Vin rouge de Bordeaux', ['sulfites']],
            'armagnac' => ['Armagnac', ['sulfites']],
            'moelle' => ['Moelle de bœuf', []],
            'entrecote' => ['Entrecôte de bœuf', []],
            'agneau' => ['Agneau de Pauillac', []],
            'magret' => ['Magret de canard', []],
            'confit' => ['Cuisse de canard confite', []],
            'foie-gras' => ['Foie gras de canard', []],
            'graisse-canard' => ['Graisse de canard', []],
            'porc' => ['Panse de porc', []],
            'jambon' => ['Jambon de Bayonne', []],
            'huitre' => ['Huître du Bassin', ['mollusques']],
            'lamproie' => ['Lamproie', ['poissons']],
            'cepe' => ['Cèpe de Gironde', []],
            'pomme-de-terre' => ['Pomme de terre', []],
            'haricot-tarbais' => ['Haricot tarbais', []],
            'asperge' => ['Asperge blanche du Blayais', []],
            'laitue' => ['Laitue', []],
            'gesier' => ['Gésier de canard', []],
            'noix' => ['Noix du Périgord', ['fruits-a-coque']],
            'amande' => ['Amande', ['fruits-a-coque']],
            'pruneau' => ['Pruneau d\'Agen', []],
            'sucre' => ['Sucre', []],
            'vanille' => ['Vanille', []],
            'rhum' => ['Rhum ambré', ['sulfites']],
            'moutarde' => ['Moutarde', ['moutarde']],
            'celeri' => ['Céleri branche', ['celeri']],
            'vinaigre' => ['Vinaigre de vin', ['sulfites']],
            'chapon' => ['Chapon fermier', []],
            'gigot' => ['Gigot d\'agneau', []],
            'saumon-fume' => ['Saumon fumé', ['poissons']],
            'saint-jacques' => ['Noix de Saint-Jacques', ['mollusques']],
            'chocolat' => ['Chocolat noir', ['soja']],
            'citron' => ['Citron', []],
            'aneth' => ['Aneth', []],
            'marron' => ['Marron', []],
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
            'huitres' => ['Huîtres du Bassin d\'Arcachon', 'Entrée', "Servies nature, avec pain de seigle et beurre demi-sel.", ['huitre', 'pain', 'beurre', 'vinaigre', 'echalote']],
            'grenier' => ['Grenier médocain', 'Entrée', "Charcuterie girondine traditionnelle, tranchée fin et servie froide.", ['porc', 'moutarde', 'thym']],
            'foie-gras' => ['Foie gras de canard mi-cuit', 'Entrée', "Mi-cuit maison, accompagné de pruneaux à l\'armagnac.", ['foie-gras', 'pruneau', 'armagnac', 'pain']],
            'tourin' => ['Tourin blanchi à l\'ail', 'Entrée', "La soupe à l\'ail du Sud-Ouest, liée à l\'œuf.", ['ail', 'oeuf', 'farine', 'vinaigre']],
            'asperges' => ['Asperges blanches du Blayais', 'Entrée', "Asperges de saison, sauce mousseline.", ['asperge', 'oeuf', 'beurre', 'moutarde']],
            'salade-landaise' => ['Salade landaise', 'Entrée', "Laitue, gésiers confits et noix du Périgord.", ['laitue', 'gesier', 'noix', 'moutarde', 'vinaigre']],
            'entrecote' => ['Entrecôte à la bordelaise', 'Plat', "Entrecôte grillée, sauce au vin rouge, échalote et moelle.", ['entrecote', 'vin-rouge', 'echalote', 'moelle', 'beurre', 'persil']],
            'lamproie' => ['Lamproie à la bordelaise', 'Plat', "Mijotée au vin rouge et aux poireaux, la recette la plus girondine qui soit.", ['lamproie', 'vin-rouge', 'echalote', 'ail', 'thym']],
            'magret' => ['Magret de canard', 'Plat', "Cuit rosé, déglacé au vin rouge.", ['magret', 'vin-rouge', 'echalote', 'thym']],
            'confit' => ['Confit de canard', 'Plat', "Cuisse confite dans sa graisse, peau croustillante.", ['confit', 'graisse-canard', 'ail', 'thym']],
            'agneau' => ['Agneau de Pauillac', 'Plat', "Carré d\'agneau rôti, ail et thym.", ['agneau', 'ail', 'thym', 'beurre']],
            'cepes' => ['Cèpes à la bordelaise', 'Accompagnement', "Cèpes poêlés à l\'échalote et au persil.", ['cepe', 'echalote', 'persil', 'ail']],
            'sarladaises' => ['Pommes sarladaises', 'Accompagnement', "Pommes de terre sautées à la graisse de canard, ail et persil.", ['pomme-de-terre', 'graisse-canard', 'ail', 'persil']],
            'haricots' => ['Haricots tarbais', 'Accompagnement', "Mijotés au jambon de Bayonne.", ['haricot-tarbais', 'jambon', 'celeri', 'thym']],
            'gratin' => ['Gratin de légumes', 'Accompagnement', "Légumes de saison gratinés à la crème.", ['pomme-de-terre', 'creme', 'lait', 'beurre', 'ail']],
            'caneles' => ['Canelés de Bordeaux', 'Dessert', "Croûte caramélisée, cœur moelleux au rhum et à la vanille.", ['farine', 'lait', 'oeuf', 'sucre', 'beurre', 'rhum', 'vanille']],
            'macarons' => ['Macarons de Saint-Émilion', 'Dessert', "La recette des Ursulines, à l\'amande douce.", ['amande', 'sucre', 'oeuf']],
            'dunes-blanches' => ['Dunes blanches', 'Dessert', "Petits choux garnis de crème fouettée, spécialité du Cap Ferret.", ['farine', 'oeuf', 'beurre', 'creme', 'sucre', 'lait']],
            'gateau-basque' => ['Gâteau basque', 'Dessert', "Pâte sablée et crème pâtissière à la vanille.", ['farine', 'beurre', 'oeuf', 'lait', 'sucre', 'vanille']],
            'pruneaux' => ['Pruneaux à l\'armagnac', 'Dessert', "Pruneaux d\'Agen macérés, servis avec une glace vanille.", ['pruneau', 'armagnac', 'creme', 'sucre', 'vanille']],
            'saumon' => ['Saumon fumé et blinis', 'Entrée', "Saumon fumé, blinis tièdes et crème citronnée à l\'aneth.", ['saumon-fume', 'farine', 'creme', 'citron', 'aneth', 'oeuf']],
            'saint-jacques' => ['Noix de Saint-Jacques poêlées', 'Entrée', "Saisies au beurre, réduction d\'échalote à la crème.", ['saint-jacques', 'beurre', 'echalote', 'creme']],
            'chapon' => ['Chapon farci aux cèpes', 'Plat', "Chapon fermier farci aux cèpes et aux marrons.", ['chapon', 'cepe', 'marron', 'pain', 'beurre', 'thym']],
            'gigot-pascal' => ['Gigot d\'agneau pascal', 'Plat', "Gigot rôti sept heures, ail et thym.", ['gigot', 'ail', 'thym', 'beurre']],
            'buche' => ['Bûche de Noël', 'Dessert', "Biscuit roulé, ganache au chocolat noir.", ['farine', 'oeuf', 'sucre', 'chocolat', 'creme', 'beurre']],
            'moelleux' => ['Moelleux au chocolat', 'Dessert', "Cœur coulant, chocolat noir de couverture.", ['chocolat', 'oeuf', 'beurre', 'sucre', 'farine']],
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
            'bistrot' => ['Bistrot bordelais', "Les classiques des tables de la ville, sans chichi."],
            'terroir' => ['Terroir du Sud-Ouest', "Canard, cèpes et produits de la région."],
            'festif' => ['Buffet festif', "Formules généreuses pour mariages, anniversaires et grandes tablées."],
            'cocktail' => ['Cocktail dînatoire', "Bouchées et pièces salées à partager debout."],
            'brunch' => ['Brunch', "Formule du week-end, sucrée et salée."],
            'noel' => ['Noël', "Menus de fête, à commander tôt : les quantités sont limitées."],
            'reveillon' => ['Réveillon du Nouvel An', "Formules festives pour la nuit du 31 décembre."],
            'paques' => ['Pâques', "Agneau, asperges et chocolat, autour du week-end pascal."],
            'saint-valentin' => ['Saint-Valentin', "Formules pour deux, à retirer ou à livrer."],
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
        // Le champ `precautions` ne liste PAS les allergènes : ceux-ci sont
        // calculés depuis les ingrédients par AllergeneRepository::findPourMenu().
        // Les écrire ici aussi créerait une seconde source de vérité, qui
        // divergerait dès qu'un plat change de composition.
        //
        // titre, thème, régime, min pers., prix, délai, stock, précautions, plats
        $definitions = [
            'bistrot-bordelais' => [
                'Bistrot Bordelais', 'bistrot', 'standard', 6, '24.00', 3, 12,
                "L\'entrecôte est livrée saignante ; précisez la cuisson souhaitée à la commande.",
                ['grenier', 'entrecote', 'cepes', 'sarladaises', 'caneles'],
            ],
            'table-medoc' => [
                'Table du Médoc', 'terroir', 'standard', 8, '34.00', 5, 6,
                "L\'agneau est rôti rosé. Prévoir un four pour la remise en température.",
                ['foie-gras', 'agneau', 'cepes', 'sarladaises', 'macarons'],
            ],
            'grand-sud-ouest' => [
                'Grand Sud-Ouest', 'terroir', 'standard', 8, '29.00', 4, 8,
                "Le confit se réchauffe 20 minutes à 180 °C pour retrouver une peau croustillante.",
                ['salade-landaise', 'confit', 'sarladaises', 'haricots', 'gateau-basque'],
            ],
            'bassin-arcachon' => [
                'Bassin d\'Arcachon', 'bistrot', 'sans-porc', 6, '31.00', 4, 7,
                "Les huîtres sont livrées non ouvertes, à consommer dans les 24 heures.",
                ['huitres', 'lamproie', 'sarladaises', 'dunes-blanches'],
            ],
            'canard-compagnie' => [
                'Canard & Compagnie', 'terroir', 'sans-porc', 8, '28.00', 4, 9,
                "Sortir le foie gras du réfrigérateur 15 minutes avant le service.",
                ['foie-gras', 'magret', 'sarladaises', 'gratin', 'pruneaux'],
            ],
            'buffet-mariage' => [
                'Buffet Mariage', 'festif', 'standard', 40, '42.00', 15, 3,
                "Composition adaptable sur demande. Prévoir un espace réfrigéré sur le lieu de réception.",
                ['huitres', 'foie-gras', 'grenier', 'entrecote', 'confit', 'cepes', 'sarladaises', 'haricots', 'caneles', 'macarons'],
            ],
            'buffet-anniversaire' => [
                'Buffet Anniversaire', 'festif', 'sans-porc', 20, '32.00', 10, 5,
                "Livré en plats de service consignés, à restituer sous 48 heures.",
                ['salade-landaise', 'magret', 'sarladaises', 'gratin', 'caneles'],
            ],
            'cocktail-girondin' => [
                'Cocktail Girondin', 'cocktail', 'standard', 15, '16.00', 5, 10,
                "Pièces servies froides, à maintenir au frais jusqu\'au service.",
                ['huitres', 'grenier', 'foie-gras'],
            ],
            'brunch-bordelais' => [
                'Brunch Bordelais', 'brunch', 'vegetarien', 4, '21.00', 2, 10,
                "Le tourin se sert très chaud : prévoir une remise en température sur place.",
                ['tourin', 'asperges', 'gratin', 'caneles', 'dunes-blanches'],
            ],
            'table-sans-gluten' => [
                'Table Sans Gluten', 'bistrot', 'sans-gluten', 6, '26.00', 4, 7,
                "Préparé sans ingrédient contenant du gluten. Notre atelier manipule par ailleurs des farines : une trace ne peut être totalement exclue.",
                ['magret', 'cepes', 'sarladaises', 'gratin', 'pruneaux'],
            ],

            // Menus ajoutés pour équilibrer le catalogue : chaque thème et
            // chaque régime doit proposer plusieurs choix, sinon un filtre du
            // site public renvoie une page à une seule entrée.
            'cocktail-vigneron' => [
                'Cocktail Vigneron', 'cocktail', 'standard', 15, '18.00', 5, 10,
                "Pièces servies froides, à maintenir au frais jusqu\'au service.",
                ['grenier', 'foie-gras', 'macarons'],
            ],
            'cocktail-mer' => [
                'Cocktail Marin', 'cocktail', 'sans-porc', 12, '22.00', 5, 8,
                "Les huîtres sont livrées non ouvertes, à consommer dans les 24 heures.",
                ['huitres', 'saint-jacques', 'dunes-blanches'],
            ],
            'brunch-marche' => [
                'Brunch du Marché', 'brunch', 'standard', 4, '23.00', 2, 12,
                "Les blinis se réchauffent deux minutes à la poêle, sans matière grasse.",
                ['saumon', 'asperges', 'gratin', 'caneles'],
            ],
            'brunch-vegetal' => [
                'Brunch Végétal', 'brunch', 'vegetarien', 4, '19.00', 2, 12,
                "Les choux se garnissent au dernier moment pour rester croustillants.",
                ['tourin', 'asperges', 'gratin', 'dunes-blanches'],
            ],
            'potager-girondin' => [
                'Potager Girondin', 'terroir', 'vegetarien', 6, '20.00', 3, 15,
                "Légumes de saison : la composition peut varier légèrement selon le marché.",
                ['asperges', 'tourin', 'cepes', 'gratin', 'gateau-basque'],
            ],
            'festif-sans-gluten' => [
                'Buffet Sans Gluten', 'festif', 'sans-gluten', 20, '36.00', 10, 0,
                "Préparé sans ingrédient contenant du gluten. Notre atelier manipule par ailleurs des farines : une trace ne peut être totalement exclue.",
                ['saint-jacques', 'magret', 'cepes', 'sarladaises', 'pruneaux'],
            ],

            // Thèmes événementiels. Attention : l'entité Menu ne porte aucune
            // période de validité. Hors saison, c'est le stock mis à zéro qui
            // retire le menu du catalogue, puisque findCatalogue() écarte les
            // menus en rupture.
            'noel-tradition' => [
                'Noël Tradition', 'noel', 'standard', 6, '45.00', 10, 8,
                "Commandes closes le 15 décembre. Le chapon est livré cuit, à remettre en température une heure avant le service.",
                ['huitres', 'foie-gras', 'chapon', 'sarladaises', 'buche'],
            ],
            'noel-marin' => [
                'Noël Marin', 'noel', 'sans-porc', 6, '52.00', 10, 5,
                "Commandes closes le 15 décembre. Huîtres livrées non ouvertes.",
                ['huitres', 'saint-jacques', 'chapon', 'gratin', 'buche'],
            ],
            'reveillon-prestige' => [
                'Réveillon Prestige', 'reveillon', 'standard', 8, '58.00', 12, 4,
                "Commandes closes le 20 décembre. Livraison possible jusqu\'à 19 h le 31.",
                ['saumon', 'saint-jacques', 'chapon', 'cepes', 'sarladaises', 'buche'],
            ],
            'reveillon-cocktail' => [
                'Réveillon Cocktail', 'reveillon', 'standard', 20, '28.00', 10, 6,
                "Commandes closes le 20 décembre. Pièces servies froides.",
                ['huitres', 'saumon', 'foie-gras', 'macarons', 'dunes-blanches'],
            ],
            'paques-tradition' => [
                'Pâques Tradition', 'paques', 'standard', 6, '34.00', 7, 8,
                "Le gigot cuit sept heures est livré chaud : à servir dans l\'heure.",
                ['asperges', 'gigot-pascal', 'gratin', 'sarladaises', 'gateau-basque'],
            ],
            'paques-vegetal' => [
                'Pâques Végétal', 'paques', 'vegetarien', 4, '24.00', 5, 10,
                "Le moelleux se réchauffe cinq minutes à 180 °C pour retrouver son cœur coulant.",
                ['tourin', 'asperges', 'gratin', 'moelleux'],
            ],
            'valentin-duo' => [
                'Saint-Valentin en Duo', 'saint-valentin', 'sans-porc', 2, '48.00', 5, 12,
                "Formule calibrée pour deux personnes exactement.",
                ['saint-jacques', 'magret', 'cepes', 'moelleux'],
            ],
            'valentin-marin' => [
                'Saint-Valentin Marin', 'saint-valentin', 'sans-porc', 2, '54.00', 5, 8,
                "Formule calibrée pour deux personnes. Huîtres livrées non ouvertes.",
                ['huitres', 'saumon', 'saint-jacques', 'gratin', 'moelleux'],
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

            if (isset(self::PERIODES[$cle])) {
                [$debut, $fin] = self::PERIODES[$cle];
                $menu->setDateDebut(new \DateTime($debut))->setDateFin(new \DateTime($fin));
            }

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
            'admin' => ['admin@vite-gourmand.fr', 'Kenny', 'Pignolet', ['ROLE_ADMIN'], '05 56 12 34 56', "12 cours de l\'Intendance, 33000 Bordeaux", true],
            'employe' => ['employe@vite-gourmand.fr', 'Marie', 'Lasserre', ['ROLE_EMPLOYE'], '05 56 23 45 67', "8 rue Sainte-Catherine, 33000 Bordeaux", true],
            'client1' => ['sophie.brunet@example.fr', 'Sophie', 'Brunet', [], '06 12 34 56 78', "24 rue Notre-Dame, 33000 Bordeaux", true],
            'client2' => ['david.marchand@example.fr', 'David', 'Marchand', [], '06 23 45 67 89', "5 avenue de la Libération, 33700 Mérignac", true],
            'client3' => ['laetitia.fontaine@example.fr', 'Laëtitia', 'Fontaine', [], null, "17 allée des Vignes, 33330 Saint-Émilion", true],
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
        // Le prix n'est plus écrit ici : il est calculé par CalculateurPrix, ce
        // qui garantit que le jeu de données respecte les règles de tarification
        // au lieu de les contredire.
        //
        // client, menu, jours écoulés depuis la commande, jours avant prestation,
        // heure, lieu, nb personnes, statut, prêt de matériel
        $definitions = [
            'livree1' => ['client1', 'bistrot-bordelais', -30, -22, '12:00', "24 rue Notre-Dame, 33000 Bordeaux", 10, Commande::LIVREE, true],
            'livree2' => ['client2', 'grand-sud-ouest', -20, -14, '19:30', "5 avenue de la Libération, 33700 Mérignac", 8, Commande::LIVREE, false],
            'livree3' => ['client1', 'brunch-bordelais', -15, -9, '11:30', "24 rue Notre-Dame, 33000 Bordeaux", 6, Commande::LIVREE, false],
            'livree4' => ['client3', 'table-sans-gluten', -12, -5, '12:00', "17 allée des Vignes, 33330 Saint-Émilion", 6, Commande::LIVREE, false],
            'preparation' => ['client3', 'buffet-anniversaire', -6, 2, '11:00', "17 allée des Vignes, 33330 Saint-Émilion", 25, Commande::EN_PREPARATION, true],
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

            // Effectif, total et remise sont posés d'un seul geste.
            $commande->appliquerPrix($this->calculateurPrix->calculer($menus[$menu], $nbPersonnes));

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
            ['livree1', 5, "Entrecôte parfaitement cuite et la sauce bordelaise était à tomber. Livraison pile à l\'heure, on recommandera.", Avis::VALIDE, -20],
            ['livree2', 4, "Très bon confit, peau bien croustillante. Un peu juste sur les haricots pour huit personnes.", Avis::VALIDE, -12],
            ['livree3', 5, "Enfin un traiteur qui soigne le végétarien. Le tourin était une vraie surprise, et les canelés impeccables.", Avis::VALIDE, -7],
            ['livree4', 3, "Bon dans l\'ensemble, mais les pruneaux à l\'armagnac sont arrivés écrasés.", Avis::EN_ATTENTE, -2],
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
            ["Livraison hors agglomération", "Livrez-vous jusqu\'au Cap Ferret ? Merci d\'avance.", 'vacancier@example.fr', -1],
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

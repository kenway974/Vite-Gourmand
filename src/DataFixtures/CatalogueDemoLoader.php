<?php

namespace App\DataFixtures;

use App\Entity\Allergene;
use App\Entity\Horaire;
use App\Entity\Ingredient;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Theme;
use App\Entity\ZoneLivraison;
use Doctrine\Persistence\ObjectManager;

/**
 * Construit le catalogue de démonstration : allergènes, ingrédients, plats,
 * thèmes, régimes, menus, horaires et zones de livraison.
 *
 * C'est la seule partie du jeu de données de AppFixtures qui peut être
 * chargée sans risque, y compris en production : elle ne crée ni compte
 * utilisateur ni commande, donc n'expose jamais le mot de passe de
 * démonstration (AppFixtures::MOT_DE_PASSE). AppFixtures s'en sert pour le
 * jeu de données complet en local ; ChargerCatalogueDemoCommand s'en sert
 * seule, pour peupler une base de production vide sans rien y ajouter de
 * sensible. Un seul endroit à tenir à jour pour les deux usages.
 */
class CatalogueDemoLoader
{
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

    /**
     * @return array{menus: array<string, Menu>, zones: array<string, ZoneLivraison>}
     */
    public function charger(ObjectManager $manager): array
    {
        $allergenes = $this->chargerAllergenes($manager);
        $ingredients = $this->chargerIngredients($manager, $allergenes);
        $plats = $this->chargerPlats($manager, $ingredients);
        $themes = $this->chargerThemes($manager);
        $regimes = $this->chargerRegimes($manager);
        $menus = $this->chargerMenus($manager, $themes, $regimes, $plats);

        $this->chargerHoraires($manager);
        $zones = $this->chargerZones($manager);

        return ['menus' => $menus, 'zones' => $zones];
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
            'foie-gras' => ['Foie gras de canard mi-cuit', 'Entrée', "Mi-cuit maison, accompagné de pruneaux à l'armagnac.", ['foie-gras', 'pruneau', 'armagnac', 'pain']],
            'tourin' => ['Tourin blanchi à l\'ail', 'Entrée', "La soupe à l'ail du Sud-Ouest, liée à l'œuf.", ['ail', 'oeuf', 'farine', 'vinaigre']],
            'asperges' => ['Asperges blanches du Blayais', 'Entrée', "Asperges de saison, sauce mousseline.", ['asperge', 'oeuf', 'beurre', 'moutarde']],
            'salade-landaise' => ['Salade landaise', 'Entrée', "Laitue, gésiers confits et noix du Périgord.", ['laitue', 'gesier', 'noix', 'moutarde', 'vinaigre']],
            'entrecote' => ['Entrecôte à la bordelaise', 'Plat', "Entrecôte grillée, sauce au vin rouge, échalote et moelle.", ['entrecote', 'vin-rouge', 'echalote', 'moelle', 'beurre', 'persil']],
            'lamproie' => ['Lamproie à la bordelaise', 'Plat', "Mijotée au vin rouge et aux poireaux, la recette la plus girondine qui soit.", ['lamproie', 'vin-rouge', 'echalote', 'ail', 'thym']],
            'magret' => ['Magret de canard', 'Plat', "Cuit rosé, déglacé au vin rouge.", ['magret', 'vin-rouge', 'echalote', 'thym']],
            'confit' => ['Confit de canard', 'Plat', "Cuisse confite dans sa graisse, peau croustillante.", ['confit', 'graisse-canard', 'ail', 'thym']],
            'agneau' => ['Agneau de Pauillac', 'Plat', "Carré d'agneau rôti, ail et thym.", ['agneau', 'ail', 'thym', 'beurre']],
            'cepes' => ['Cèpes à la bordelaise', 'Accompagnement', "Cèpes poêlés à l'échalote et au persil.", ['cepe', 'echalote', 'persil', 'ail']],
            'sarladaises' => ['Pommes sarladaises', 'Accompagnement', "Pommes de terre sautées à la graisse de canard, ail et persil.", ['pomme-de-terre', 'graisse-canard', 'ail', 'persil']],
            'haricots' => ['Haricots tarbais', 'Accompagnement', "Mijotés au jambon de Bayonne.", ['haricot-tarbais', 'jambon', 'celeri', 'thym']],
            'gratin' => ['Gratin de légumes', 'Accompagnement', "Légumes de saison gratinés à la crème.", ['pomme-de-terre', 'creme', 'lait', 'beurre', 'ail']],
            'caneles' => ['Canelés de Bordeaux', 'Dessert', "Croûte caramélisée, cœur moelleux au rhum et à la vanille.", ['farine', 'lait', 'oeuf', 'sucre', 'beurre', 'rhum', 'vanille']],
            'macarons' => ['Macarons de Saint-Émilion', 'Dessert', "La recette des Ursulines, à l'amande douce.", ['amande', 'sucre', 'oeuf']],
            'dunes-blanches' => ['Dunes blanches', 'Dessert', "Petits choux garnis de crème fouettée, spécialité du Cap Ferret.", ['farine', 'oeuf', 'beurre', 'creme', 'sucre', 'lait']],
            'gateau-basque' => ['Gâteau basque', 'Dessert', "Pâte sablée et crème pâtissière à la vanille.", ['farine', 'beurre', 'oeuf', 'lait', 'sucre', 'vanille']],
            'pruneaux' => ['Pruneaux à l\'armagnac', 'Dessert', "Pruneaux d'Agen macérés, servis avec une glace vanille.", ['pruneau', 'armagnac', 'creme', 'sucre', 'vanille']],
            'saumon' => ['Saumon fumé et blinis', 'Entrée', "Saumon fumé, blinis tièdes et crème citronnée à l'aneth.", ['saumon-fume', 'farine', 'creme', 'citron', 'aneth', 'oeuf']],
            'saint-jacques' => ['Noix de Saint-Jacques poêlées', 'Entrée', "Saisies au beurre, réduction d'échalote à la crème.", ['saint-jacques', 'beurre', 'echalote', 'creme']],
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
     * @param class-string<Theme|Regime>           $classe
     * @param array<string, array{string, string}> $definitions
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
                "L'entrecôte est livrée saignante ; précisez la cuisson souhaitée à la commande.",
                ['grenier', 'entrecote', 'cepes', 'sarladaises', 'caneles'],
            ],
            'table-medoc' => [
                'Table du Médoc', 'terroir', 'standard', 8, '34.00', 5, 6,
                "L'agneau est rôti rosé. Prévoir un four pour la remise en température.",
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
                "Pièces servies froides, à maintenir au frais jusqu'au service.",
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
                "Pièces servies froides, à maintenir au frais jusqu'au service.",
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
                "Commandes closes le 20 décembre. Livraison possible jusqu'à 19 h le 31.",
                ['saumon', 'saint-jacques', 'chapon', 'cepes', 'sarladaises', 'buche'],
            ],
            'reveillon-cocktail' => [
                'Réveillon Cocktail', 'reveillon', 'standard', 20, '28.00', 10, 6,
                "Commandes closes le 20 décembre. Pièces servies froides.",
                ['huitres', 'saumon', 'foie-gras', 'macarons', 'dunes-blanches'],
            ],
            'paques-tradition' => [
                'Pâques Tradition', 'paques', 'standard', 6, '34.00', 7, 8,
                "Le gigot cuit sept heures est livré chaud : à servir dans l'heure.",
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

    private function chargerHoraires(ObjectManager $manager): void
    {
        // Le dimanche n'a pas d'heures : il est fermé, pas ouvert de minuit
        // à minuit. L'ordre chronologique est porté par Horaire::setJour().
        $semaine = [
            ['Lundi', '09:00', '18:00'],
            ['Mardi', '09:00', '18:00'],
            ['Mercredi', '09:00', '18:00'],
            ['Jeudi', '09:00', '18:00'],
            ['Vendredi', '09:00', '19:00'],
            ['Samedi', '09:00', '13:00'],
            ['Dimanche', null, null],
        ];

        foreach ($semaine as [$jour, $ouverture, $fermeture]) {
            $horaire = (new Horaire())
                ->setJour($jour)
                ->setFerme(null === $ouverture);

            if (null !== $ouverture) {
                $horaire
                    ->setHeureOuverture(new \DateTime($ouverture))
                    ->setHeureFermeture(new \DateTime($fermeture));
            }

            $manager->persist($horaire);
        }
    }

    /**
     * Communes desservies.
     *
     * Bordeaux et ses codes postaux internes sont livrés sans supplément,
     * conformément aux maquettes. La première couronne est desservie avec un
     * supplément que le traiteur reste libre de modifier depuis
     * l'administration : les maquettes n'en fixent aucun.
     *
     * @return array<string, ZoneLivraison>
     */
    private function chargerZones(ObjectManager $manager): array
    {
        $definitions = [
            // code postal, commune, supplément
            ['33000', 'Bordeaux', '0.00'],
            ['33100', 'Bordeaux (Bastide)', '0.00'],
            ['33200', 'Bordeaux (Caudéran)', '0.00'],
            ['33300', 'Bordeaux (Chartrons)', '0.00'],
            ['33800', 'Bordeaux (Saint-Jean)', '0.00'],
            ['33110', 'Le Bouscat', '25.00'],
            ['33130', 'Bègles', '25.00'],
            ['33150', 'Cenon', '25.00'],
            ['33170', 'Gradignan', '35.00'],
            ['33270', 'Floirac', '25.00'],
            ['33310', 'Lormont', '25.00'],
            ['33400', 'Talence', '25.00'],
            ['33600', 'Pessac', '35.00'],
            ['33700', 'Mérignac', '35.00'],
        ];

        $zones = [];

        foreach ($definitions as [$code, $commune, $frais]) {
            $zone = (new ZoneLivraison())
                ->setCodePostal($code)
                ->setCommune($commune)
                ->setFrais($frais);

            $manager->persist($zone);
            $zones[$code] = $zone;
        }

        return $zones;
    }
}

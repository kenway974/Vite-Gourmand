-- Jeu de donnees de demonstration - Vite & Gourmand
--
-- GENERE AUTOMATIQUEMENT depuis src/DataFixtures/AppFixtures.php.
-- Ne pas editer a la main : modifier les fixtures puis regenerer.
--
-- Prerequis : le schema doit exister (php bin/console doctrine:migrations:migrate).
-- Dialecte : MySQL / MariaDB, comme la migration du projet.
--
-- Mot de passe de tous les comptes : Motdepasse&974!
-- Compte administrateur : admin@vite-gourmand.fr

SET FOREIGN_KEY_CHECKS = 0;

-- Purge (ordre inverse des dependances)
DELETE FROM `contact`;
DELETE FROM `horaire`;
DELETE FROM `avis`;
DELETE FROM `suivi_commande`;
DELETE FROM `commande`;
DELETE FROM `utilisateur`;
DELETE FROM `menu_plat`;
DELETE FROM `menu`;
DELETE FROM `plat_ingredient`;
DELETE FROM `plat`;
DELETE FROM `regime`;
DELETE FROM `theme`;
DELETE FROM `ingredient_allergene`;
DELETE FROM `ingredient`;
DELETE FROM `allergene`;

-- allergene (14 lignes)
INSERT INTO `allergene` (`id`, `libelle`) VALUES
  (1, 'Gluten'),
  (2, 'Crustacés'),
  (3, 'Œufs'),
  (4, 'Poissons'),
  (5, 'Arachides'),
  (6, 'Soja'),
  (7, 'Lait'),
  (8, 'Fruits à coque'),
  (9, 'Céleri'),
  (10, 'Moutarde'),
  (11, 'Graines de sésame'),
  (12, 'Sulfites'),
  (13, 'Lupin'),
  (14, 'Mollusques');

-- ingredient (30 lignes)
INSERT INTO `ingredient` (`id`, `nom`, `image`) VALUES
  (1, 'Farine de blé', NULL),
  (2, 'Œuf', NULL),
  (3, 'Lait', NULL),
  (4, 'Beurre', NULL),
  (5, 'Crème fraîche', NULL),
  (6, 'Riz', NULL),
  (7, 'Haricots rouges', NULL),
  (8, 'Tomate', NULL),
  (9, 'Oignon', NULL),
  (10, 'Ail', NULL),
  (11, 'Gingembre', NULL),
  (12, 'Curcuma', NULL),
  (13, 'Piment', NULL),
  (14, 'Thym', NULL),
  (15, 'Poulet fermier', NULL),
  (16, 'Échine de porc', NULL),
  (17, 'Saucisse fumée', NULL),
  (18, 'Crevette', NULL),
  (19, 'Thon', NULL),
  (20, 'Cabillaud', NULL),
  (21, 'Sauce soja', NULL),
  (22, 'Cacahuète', NULL),
  (23, 'Amande', NULL),
  (24, 'Graine de sésame', NULL),
  (25, 'Moutarde', NULL),
  (26, 'Vanille Bourbon', NULL),
  (27, 'Patate douce', NULL),
  (28, 'Noix de coco râpée', NULL),
  (29, 'Sucre de canne', NULL),
  (30, 'Cœur de palmiste', NULL);

-- ingredient_allergene (15 lignes)
INSERT INTO `ingredient_allergene` (`ingredient_id`, `allergene_id`) VALUES
  (1, 1),
  (2, 3),
  (3, 7),
  (4, 7),
  (5, 7),
  (17, 12),
  (18, 2),
  (19, 4),
  (20, 4),
  (21, 6),
  (21, 1),
  (22, 5),
  (23, 8),
  (24, 11),
  (25, 10);

-- theme (5 lignes)
INSERT INTO `theme` (`id`, `libelle`, `description`) VALUES
  (1, 'Créole traditionnel', 'Les classiques de la cuisine réunionnaise, cuisinés comme à la maison.'),
  (2, 'Buffet festif', 'Formules généreuses pour mariages, anniversaires et grandes tablées.'),
  (3, 'Cocktail dînatoire', 'Bouchées et pièces salées à partager debout.'),
  (4, 'Cuisine métropolitaine', 'Des recettes de l\'Hexagone, pour changer du cari.'),
  (5, 'Brunch', 'Formule du week-end, sucrée et salée.');

-- regime (4 lignes)
INSERT INTO `regime` (`id`, `libelle`, `description`) VALUES
  (1, 'Standard', 'Sans restriction particulière.'),
  (2, 'Végétarien', 'Sans viande ni poisson.'),
  (3, 'Sans porc', 'Aucune préparation à base de porc.'),
  (4, 'Sans gluten', 'Adapté aux intolérants au gluten.');

-- plat (16 lignes)
INSERT INTO `plat` (`id`, `nom`, `type`, `description`, `image`) VALUES
  (1, 'Samoussas au thon', 'Entrée', 'Petits triangles croustillants garnis de thon relevé au curcuma.', NULL),
  (2, 'Bouchons créoles', 'Entrée', 'Bouchons vapeur au porc et au gingembre, servis avec leur sauce.', NULL),
  (3, 'Salade de cœur de palmiste', 'Entrée', 'Le « millionnaire » réunionnais, en salade fraîche.', NULL),
  (4, 'Rougail saucisse', 'Plat', 'Le plat emblématique de La Réunion : saucisses fumées mijotées en sauce tomate épicée.', NULL),
  (5, 'Cari de poulet', 'Plat', 'Poulet fermier mijoté au curcuma, gingembre et oignon.', NULL),
  (6, 'Civet de porc', 'Plat', 'Échine de porc longuement mijotée, relevée au thym et au piment.', NULL),
  (7, 'Cari de crevettes', 'Plat', 'Crevettes saisies puis mijotées en sauce créole.', NULL),
  (8, 'Massalé de cabillaud', 'Plat', 'Dos de cabillaud au massalé, doux et parfumé.', NULL),
  (9, 'Cari de légumes', 'Plat', 'Version végétarienne du cari, généreuse en légumes de saison.', NULL),
  (10, 'Riz blanc', 'Accompagnement', 'Riz parfumé, cuisson vapeur.', NULL),
  (11, 'Grains (haricots rouges)', 'Accompagnement', 'Haricots rouges mijotés, l\'accompagnement indissociable du cari.', NULL),
  (12, 'Rougail tomate', 'Accompagnement', 'Condiment frais et pimenté, à doser selon le courage.', NULL),
  (13, 'Achards de légumes', 'Accompagnement', 'Légumes croquants marinés au curcuma.', NULL),
  (14, 'Gâteau patate douce', 'Dessert', 'Le dessert créole par excellence, moelleux et parfumé à la vanille.', NULL),
  (15, 'Tarte à la vanille Bourbon', 'Dessert', 'Pâte sablée et crème à la vanille de Bourbon Pointu.', NULL),
  (16, 'Salade de fruits tropicaux', 'Dessert', 'Ananas Victoria, mangue et litchi selon la saison.', NULL);

-- plat_ingredient (63 lignes)
INSERT INTO `plat_ingredient` (`plat_id`, `ingredient_id`) VALUES
  (1, 1),
  (1, 19),
  (1, 9),
  (1, 12),
  (2, 1),
  (2, 16),
  (2, 11),
  (2, 21),
  (3, 30),
  (3, 8),
  (3, 25),
  (4, 17),
  (4, 8),
  (4, 9),
  (4, 10),
  (4, 13),
  (4, 14),
  (5, 15),
  (5, 12),
  (5, 11),
  (5, 9),
  (5, 10),
  (6, 16),
  (6, 9),
  (6, 10),
  (6, 14),
  (6, 13),
  (7, 18),
  (7, 8),
  (7, 12),
  (7, 10),
  (8, 20),
  (8, 12),
  (8, 11),
  (8, 8),
  (9, 27),
  (9, 8),
  (9, 9),
  (9, 12),
  (9, 10),
  (10, 6),
  (11, 7),
  (11, 9),
  (11, 14),
  (12, 8),
  (12, 9),
  (12, 13),
  (13, 12),
  (13, 10),
  (13, 25),
  (14, 27),
  (14, 2),
  (14, 29),
  (14, 26),
  (14, 4),
  (15, 1),
  (15, 4),
  (15, 2),
  (15, 3),
  (15, 26),
  (15, 29),
  (16, 29),
  (16, 28);

-- menu (10 lignes)
INSERT INTO `menu` (`id`, `titre`, `description`, `nb_min_personnes`, `prix_min`, `delai_commande_jours`, `precautions`, `stock`, `image`, `theme_id`, `regime_id`) VALUES
  (1, 'Créole Découverte', 'Formule « Créole Découverte », composée de 6 préparations : Samoussas au thon, Rougail saucisse, Riz blanc, Grains (haricots rouges), Rougail tomate, Gâteau patate douce.', 6, '18.5', 3, 'Contient du porc. Le rougail tomate est servi à part pour doser le piment.', 12, NULL, 1, 1),
  (2, 'Créole Prestige', 'Formule « Créole Prestige », composée de 8 préparations : Bouchons créoles, Samoussas au thon, Cari de crevettes, Civet de porc, Riz blanc, Grains (haricots rouges), Achards de légumes, Tarte à la vanille Bourbon.', 10, 32, 5, 'Contient crustacés, poisson et porc.', 6, NULL, 1, 1),
  (3, 'Cari de Volaille', 'Formule « Cari de Volaille », composée de 5 préparations : Salade de cœur de palmiste, Cari de poulet, Riz blanc, Grains (haricots rouges), Salade de fruits tropicaux.', 6, 21, 3, 'Aucune préparation à base de porc.', 15, NULL, 1, 3),
  (4, 'Océan Indien', 'Formule « Océan Indien », composée de 6 préparations : Samoussas au thon, Massalé de cabillaud, Cari de crevettes, Riz blanc, Achards de légumes, Salade de fruits tropicaux.', 8, '27.5', 4, 'Contient poisson et crustacés.', 8, NULL, 1, 3),
  (5, 'Jardin Créole', 'Formule « Jardin Créole », composée de 6 préparations : Salade de cœur de palmiste, Cari de légumes, Riz blanc, Grains (haricots rouges), Achards de légumes, Salade de fruits tropicaux.', 6, 16, 3, 'Entièrement végétarien.', 20, NULL, 1, 2),
  (6, 'Buffet Mariage', 'Formule « Buffet Mariage », composée de 11 préparations : Bouchons créoles, Samoussas au thon, Salade de cœur de palmiste, Rougail saucisse, Cari de poulet, Cari de crevettes, Riz blanc, Grains (haricots rouges), Achards de légumes, Gâteau patate douce, Tarte à la vanille Bourbon.', 40, 38, 15, 'Contient tous les allergènes majeurs. Composition adaptable sur demande.', 3, NULL, 2, 1),
  (7, 'Buffet Anniversaire', 'Formule « Buffet Anniversaire », composée de 6 préparations : Samoussas au thon, Cari de poulet, Massalé de cabillaud, Riz blanc, Grains (haricots rouges), Gâteau patate douce.', 20, 29, 10, 'Sans porc. Contient poisson.', 5, NULL, 2, 3),
  (8, 'Cocktail Salé', 'Formule « Cocktail Salé », composée de 3 préparations : Bouchons créoles, Samoussas au thon, Salade de cœur de palmiste.', 15, 14, 5, 'Pièces à partager, servies froides ou tièdes.', 10, NULL, 3, 1),
  (9, 'Brunch du Dimanche', 'Formule « Brunch du Dimanche », composée de 5 préparations : Salade de cœur de palmiste, Cari de légumes, Riz blanc, Tarte à la vanille Bourbon, Salade de fruits tropicaux.', 4, '19.5', 2, 'Végétarien. Contient gluten, lait et œuf.', 10, NULL, 5, 2),
  (10, 'Créole Sans Gluten', 'Formule « Créole Sans Gluten », composée de 5 préparations : Cari de poulet, Riz blanc, Grains (haricots rouges), Rougail tomate, Salade de fruits tropicaux.', 6, 23, 4, 'Élaboré sans ingrédient contenant du gluten.', 7, NULL, 1, 4);

-- menu_plat (61 lignes)
INSERT INTO `menu_plat` (`plat_id`, `menu_id`) VALUES
  (1, 1),
  (1, 2),
  (1, 4),
  (1, 6),
  (1, 7),
  (1, 8),
  (2, 2),
  (2, 6),
  (2, 8),
  (3, 3),
  (3, 5),
  (3, 6),
  (3, 8),
  (3, 9),
  (4, 1),
  (4, 6),
  (5, 3),
  (5, 6),
  (5, 7),
  (5, 10),
  (6, 2),
  (7, 2),
  (7, 4),
  (7, 6),
  (8, 4),
  (8, 7),
  (9, 5),
  (9, 9),
  (10, 1),
  (10, 2),
  (10, 3),
  (10, 4),
  (10, 5),
  (10, 6),
  (10, 7),
  (10, 9),
  (10, 10),
  (11, 1),
  (11, 2),
  (11, 3),
  (11, 5),
  (11, 6),
  (11, 7),
  (11, 10),
  (12, 1),
  (12, 10),
  (13, 2),
  (13, 4),
  (13, 5),
  (13, 6),
  (14, 1),
  (14, 6),
  (14, 7),
  (15, 2),
  (15, 6),
  (15, 9),
  (16, 3),
  (16, 4),
  (16, 5),
  (16, 9),
  (16, 10);

-- utilisateur (6 lignes)
INSERT INTO `utilisateur` (`id`, `email`, `roles`, `password`, `nom`, `prenom`, `gsm`, `adresse_postale`, `actif`) VALUES
  (1, 'admin@vite-gourmand.fr', '["ROLE_ADMIN"]', '$2y$13$gErkUWatYWO7LTEof8IyK.CNoYtpnb51Uq2tri3Cs6pn0gBVRuPGq', 'Pignolet', 'Kenny', '0692 12 34 56', '12 rue de la Compagnie, 97400 Saint-Denis', 1),
  (2, 'employe@vite-gourmand.fr', '["ROLE_EMPLOYE"]', '$2y$13$W9.IXTmTx8J8pUzyj9KTeevckMSuoD1UrJBJR9.Fi0MwPODaFQTXS', 'Hoarau', 'Marie', '0692 23 45 67', '5 rue Juliette Dodu, 97400 Saint-Denis', 1),
  (3, 'sophie.grondin@example.fr', '[]', '$2y$13$AhlRQ1LcYPftHI.6MMxr5O.Jku428PD6aODpELbZHq9ShI.N5V93.', 'Grondin', 'Sophie', '0692 34 56 78', '8 chemin des Manguiers, 97490 Sainte-Clotilde', 1),
  (4, 'david.payet@example.fr', '[]', '$2y$13$LVn7/wimRFoMFW5X0rjvR.Y0arNvvgKHBS5DcoiIota0cYYCjx/Fm', 'Payet', 'David', '0692 45 67 89', '22 rue du Stade, 97410 Saint-Pierre', 1),
  (5, 'laetitia.fontaine@example.fr', '[]', '$2y$13$5esD61KbF16ms0kQa11WFO6I/t5pKPvws.tFaT02ViKBdRrOPJWUm', 'Fontaine', 'Laëtitia', NULL, '3 allée des Filaos, 97434 Saint-Gilles', 1),
  (6, 'compte.desactive@example.fr', '[]', '$2y$13$.1BEqi3fLt3h78fV8zTVAO00nCLW0CCh3pSESkzJcgwBN2w0oQjvG', 'Técher', 'Jean', NULL, NULL, 0);

-- commande (8 lignes)
INSERT INTO `commande` (`id`, `date_commande`, `date_prestation`, `heure_livraison`, `lieu_livraison`, `nb_personnes`, `prix_total`, `statut`, `pret_materiel`, `utilisateur_id`, `menu_id`) VALUES
  (1, '2026-08-17 06:18:04', '2026-08-25', '12:00:00', '8 chemin des Manguiers, 97490 Sainte-Clotilde', 10, 185, 'livrée', 1, 3, 1),
  (2, '2026-08-27 06:18:04', '2026-09-02', '19:30:00', '22 rue du Stade, 97410 Saint-Pierre', 8, 168, 'livrée', 0, 4, 3),
  (3, '2026-09-01 06:18:04', '2026-09-07', '12:30:00', '8 chemin des Manguiers, 97490 Sainte-Clotilde', 6, 96, 'livrée', 0, 3, 5),
  (4, '2026-09-04 06:18:04', '2026-09-11', '12:00:00', '3 allée des Filaos, 97434 Saint-Gilles', 6, 138, 'livrée', 0, 5, 10),
  (5, '2026-09-10 06:18:04', '2026-09-18', '11:00:00', '3 allée des Filaos, 97434 Saint-Gilles', 25, 725, 'en préparation', 1, 5, 7),
  (6, '2026-09-13 06:18:04', '2026-09-22', '19:00:00', '22 rue du Stade, 97410 Saint-Pierre', 12, 330, 'confirmée', 0, 4, 4),
  (7, '2026-09-15 06:18:04', '2026-10-11', '18:00:00', 'Domaine du Grand Hazier, 97438 Sainte-Marie', 60, 2280, 'en attente', 1, 5, 6),
  (8, '2026-09-06 06:18:04', '2026-09-14', '18:30:00', '8 chemin des Manguiers, 97490 Sainte-Clotilde', 20, 280, 'annulée', 0, 3, 8);

-- suivi_commande (25 lignes)
INSERT INTO `suivi_commande` (`id`, `statut`, `date_modification`, `motif`, `mode_contact`, `commande_id`) VALUES
  (1, 'en attente', '2026-08-17 06:18:04', NULL, 'site web', 1),
  (2, 'confirmée', '2026-08-18 06:18:04', NULL, 'email', 1),
  (3, 'en préparation', '2026-08-19 06:18:04', NULL, 'email', 1),
  (4, 'livrée', '2026-08-20 06:18:04', NULL, 'email', 1),
  (5, 'en attente', '2026-08-27 06:18:04', NULL, 'site web', 2),
  (6, 'confirmée', '2026-08-28 06:18:04', NULL, 'email', 2),
  (7, 'en préparation', '2026-08-29 06:18:04', NULL, 'email', 2),
  (8, 'livrée', '2026-08-30 06:18:04', NULL, 'email', 2),
  (9, 'en attente', '2026-09-01 06:18:04', NULL, 'site web', 3),
  (10, 'confirmée', '2026-09-02 06:18:04', NULL, 'email', 3),
  (11, 'en préparation', '2026-09-03 06:18:04', NULL, 'email', 3),
  (12, 'livrée', '2026-09-04 06:18:04', NULL, 'email', 3),
  (13, 'en attente', '2026-09-04 06:18:04', NULL, 'site web', 4),
  (14, 'confirmée', '2026-09-05 06:18:04', NULL, 'email', 4),
  (15, 'en préparation', '2026-09-06 06:18:04', NULL, 'email', 4),
  (16, 'livrée', '2026-09-07 06:18:04', NULL, 'email', 4),
  (17, 'en attente', '2026-09-10 06:18:04', NULL, 'site web', 5),
  (18, 'confirmée', '2026-09-11 06:18:04', NULL, 'email', 5),
  (19, 'en préparation', '2026-09-12 06:18:04', NULL, 'email', 5),
  (20, 'en attente', '2026-09-13 06:18:04', NULL, 'site web', 6),
  (21, 'confirmée', '2026-09-14 06:18:04', NULL, 'email', 6),
  (22, 'en attente', '2026-09-15 06:18:04', NULL, 'site web', 7),
  (23, 'en attente', '2026-09-06 06:18:04', NULL, 'site web', 8),
  (24, 'confirmée', '2026-09-07 06:18:04', NULL, 'email', 8),
  (25, 'annulée', '2026-09-08 06:18:04', 'Annulation à la demande du client, plus de 48 h avant la prestation.', 'email', 8);

-- avis (4 lignes)
INSERT INTO `avis` (`id`, `note`, `commentaire`, `statut_validation`, `date_creation`, `commande_id`, `utilisateur_id`) VALUES
  (1, 5, 'Rougail excellent, quantités généreuses. Livraison pile à l\'heure, on recommandera.', 'validé', '2026-08-27 06:18:04', 1, 3),
  (2, 4, 'Très bon cari, bien parfumé. Un peu juste sur le riz pour huit personnes.', 'validé', '2026-09-04 06:18:04', 2, 4),
  (3, 5, 'Enfin un traiteur qui soigne le végétarien. Le cari de légumes était une vraie réussite.', 'validé', '2026-09-09 06:18:04', 3, 3),
  (4, 3, 'Bon dans l\'ensemble, mais le gâteau patate est arrivé écrasé.', 'en attente', '2026-09-14 06:18:04', 4, 5);

-- horaire (7 lignes)
INSERT INTO `horaire` (`id`, `jour`, `heure_ouverture`, `heure_fermeture`) VALUES
  (1, 'Lundi', '09:00:00', '18:00:00'),
  (2, 'Mardi', '09:00:00', '18:00:00'),
  (3, 'Mercredi', '09:00:00', '18:00:00'),
  (4, 'Jeudi', '09:00:00', '18:00:00'),
  (5, 'Vendredi', '09:00:00', '19:00:00'),
  (6, 'Samedi', '09:00:00', '13:00:00'),
  (7, 'Dimanche', '00:00:00', '00:00:00');

-- contact (3 lignes)
INSERT INTO `contact` (`id`, `titre`, `message`, `email`, `date_creation`) VALUES
  (1, 'Devis pour un séminaire', 'Bonjour, nous organisons un séminaire pour 80 personnes le mois prochain. Proposez-vous des formules adaptées ?', 'contact@entreprise-974.fr', '2026-09-11 06:18:04'),
  (2, 'Question sur les allergènes', 'Ma fille est allergique aux fruits à coque. Le menu Créole Découverte lui conviendrait-il ?', 'famille.robert@example.fr', '2026-09-13 06:18:04'),
  (3, 'Livraison dans les Hauts', 'Livrez-vous jusqu\'à Cilaos ? Merci d\'avance.', 'randonneur@example.fr', '2026-09-15 06:18:04');

SET FOREIGN_KEY_CHECKS = 1;

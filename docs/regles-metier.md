# Règles métier

Relevées dans les maquettes livrées le 16/09/2026 (`docs/maquettes/`). Aucune
n'était présente dans le modèle de données ni dans le code : elles sont à
implémenter, et certaines demandent une évolution du schéma.

## 1. Le prix est unitaire, par personne

> « 48 € /pers. » — « par personne, dès 6 convives »

`Menu::prixMin` est donc un **prix par personne**, pas un montant plancher de
commande. Le nom prête à confusion.

    prix de base = prixMin × nbPersonnes

## 2. Remise de 10 % au-delà d'un seuil relatif

> « Remise de 10 % — Accordée dès onze convives, soit cinq personnes de plus
> que le minimum de ce menu. »

Le seuil n'est pas absolu, il dépend du menu :

    seuil de remise = nbMinPersonnes + 5
    si nbPersonnes >= seuil, remise de 10 %

Pour un menu à 6 convives minimum, la remise s'applique à partir de 11.

## 3. Ce que le prix inclut, et où

> « Le prix inclut la livraison, le dressage et la reprise du matériel dans un
> rayon de Bordeaux intra-muros. »

Aucun frais de livraison à ajouter **dans la zone**. Hors zone, la maquette ne
dit rien.

Tranché ainsi : les zones sont des **données**, pas un barème écrit en dur.
`ZoneLivraison` associe un code postal, une commune et un supplément, que le
traiteur gère depuis `/admin/zones`. Un code postal absent de la table n'est
pas desservi — la commande est refusée et le client renvoyé vers le
formulaire de contact pour un devis. Refuser vaut mieux que facturer un tarif
que personne n'a fixé.

Le supplément s'ajoute **après** la remise : celle-ci porte sur les
prestations, pas sur le transport. Pour un menu à 40 € et 15 convives livré
à Mérignac (35 €) : 600 − 60 + 35 = **575 €**, et non 571,50 €.

Le code postal est saisi à part de l'adresse (`Commande::codePostalLivraison`) :
l'extraire de `lieuLivraison` par expression régulière serait fragile. Le
supplément retenu est recopié sur la commande, de sorte qu'un changement de
tarif ne réécrive pas les commandes passées.

## 4. Indemnité de 600 € sur le matériel non restitué

> « Plats et présentoirs sont à restituer sous dix jours ouvrés, sans quoi une
> indemnité de 600 € s'applique. »

Se rattache à `Commande::pretMateriel`. Le délai est en **jours ouvrés**, pas
calendaires : dix jours ouvrés font deux semaines pleines.

Implémenté sur `Commande` :

- `dateLimiteRestitution()` — prestation + 10 jours ouvrés, week-ends sautés.
  Les jours fériés ne sont **pas** déduits : les maquettes n'en parlent pas, et
  les inventer avancerait la date limite au détriment du client.
- `materielEstEnRetard()` — vrai aussi pour un matériel rendu en retard. C'est
  le dépassement qui déclenche l'indemnité, pas l'absence définitive de retour.
- `restituerMateriel()` / `annulerRestitutionMateriel()` — annuler un retour
  efface aussi l'indemnité.
- `appliquerIndemniteMateriel()` — refuse tant que le délai n'est pas dépassé.
  Le montant facturé est stocké à part du barème : un geste commercial reste
  traçable.

Suivi par l'employé sur `/employe/materiel`.

## 5. Le délai de commande est ferme

> « Le chapon est réservé chez notre éleveur, nous ne pouvons pas raccourcir
> ce délai. »

`delaiCommandeJours` est un minimum non négociable :

    datePrestation >= aujourd'hui + delaiCommandeJours

Contrôle bloquant à la création d'une commande.

## 6. Conservation

> « À placer au réfrigérateur dès la livraison et à consommer dans les
> vingt-quatre heures », entre 0 et 4 °C.

Information à afficher, pas de calcul.

## 7. Deux agrégats de notation

> Accueil : « 4,8 sur 5 — 47 avis vérifiés »
> Détail : « 4,8 sur 5 — 12 avis vérifiés »

Une moyenne **globale** et une moyenne **par menu**. « Vérifiés » signifie que
seuls les avis validés sont comptés (`Avis::statutValidation`).

## 8. Les avis affichent le contexte de la commande

> « Décembre 2024 · 8 convives »

L'avis montre le nombre de convives de sa commande. Déjà possible par la
relation existante.

## 9. Engagement de réponse sous 48 h

> « Dites-nous tout, on vous répond sous 48 h. »

Implique un suivi des demandes de contact, donc un statut de traitement sur
`Contact` — champ absent aujourd'hui.

## 10. Recherche et tri au catalogue

> « Rechercher un menu… » et « Trier par : Recommandés »

Implémenté : `findCatalogue()` accepte un mot-clé et quatre ordres de tri.

La recherche ignore la casse **et les accents** : taper « pate de foi » doit
trouver « Pâté de foie ». Ce n'est pas délégué à la collation de la base —
elle varie d'un hébergeur à l'autre et n'est pas testable sur SQLite. Le texte
est normalisé en PHP (`App\Service\Normalisateur`) et stocké dans une colonne
dédiée, si bien que le comportement est identique partout et couvert par les
tests.

## 11. Filtre convives par paliers

> « 4 et plus / 6 et plus / 20 et plus »

Paliers fixes, pas un champ libre.

## 12. Badge d'état sur les vignettes

> « ANNIVERSAIRE · BIENTÔT »

Confirme la saisonnalité ajoutée sur la branche `saisonnalite-menus` :
`Menu::disponibilite()` renvoie exactement cet état.

---

# Écarts entre les maquettes et le jeu de données actuel

| Sujet | Maquettes | Fixtures actuelles |
|---|---|---|
| Thèmes | Noël, Pâques, Mariage, Entreprise, Anniversaire, Classique | Bistrot bordelais, Terroir, Buffet festif, Cocktail, Brunch, Noël, Réveillon, Pâques, Saint-Valentin |
| Régimes | Végétarien, Vegan, Sans gluten | Standard, Végétarien, Sans porc, Sans gluten |
| Adresse | 12 rue des Faussets, 33000 Bordeaux | Autres adresses |
| Téléphone | 05 56 00 00 00 | — |
| Horaires | Lundi – Vendredi, 9h – 19h | Samedi 9h – 13h en plus |
| Propriétaires | Julie & José, 25 ans de métier | — |

**Vegan** manque, **Sans porc** n'apparaît pas dans les maquettes.

---

# Manques dans le modèle de données

1. ~~**`Commande`** ne trace ni la remise appliquée ni son montant.~~
   Comblé : `tauxRemise` et `montantRemise`, posés par `appliquerPrix()`.
2. ~~**`Commande`** ne trace pas la restitution du matériel (date, indemnité).~~
   Comblé : `dateRestitutionMateriel` et `indemniteMateriel`.
3. ~~**`Contact`** n'a aucun statut de traitement.~~ Comblé : `traite`,
   `dateTraitement` et `estEnRetard()` (délai de réponse de 48 h).
4. ~~**`Horaire::jour`** est une chaîne sans ordre.~~ Comblé : colonne `ordre`,
   renseignée par `setJour()`, plus un marqueur `ferme` pour les jours sans
   service.
5. ~~**Pas de zone de livraison**, alors que le prix en dépend.~~ Comblé :
   entité `ZoneLivraison`, `Commande::codePostalLivraison` et
   `Commande::fraisLivraison`, contrainte `ZoneDesservie`.
6. **`Commande::statut`** et **`Avis::statutValidation`** sont des chaînes
   libres : une faute de frappe crée un statut fantôme.

---

# Pages à construire, d'après la navigation des maquettes

    Accueil · Nos menus · Notre histoire · Avis · Contact · Espace client

« Notre histoire » et « Avis » sont deux pages publiques que rien ne couvre
aujourd'hui.

---

# Répartition des droits (cahier des charges § 3)

> « Employé : gère les menus, les horaires, les commandes et valide les avis. »
> « Admin : fait tout ce que fait l'employé, plus la création des comptes
> employés et les statistiques. »

| Section | Employé | Admin |
|---|---|---|
| Menus, plats, ingrédients, allergènes, thèmes, régimes | ✅ | ✅ |
| Horaires d'ouverture | ✅ | ✅ |
| Commandes, avis, messages, matériel | ✅ | ✅ |
| Comptes utilisateurs | ❌ | ✅ |
| Zones de livraison | ❌ | ✅ |
| Statistiques | ❌ | ✅ |

Ingrédients, allergènes, thèmes et régimes ne sont pas cités par le cahier des
charges, mais un menu ne se compose pas sans eux : les refuser à l'employé le
bloquerait dès qu'un plat demande un ingrédient qui n'existe pas encore.

Les zones de livraison suivent les comptes plutôt que le catalogue : elles
fixent ce qui est facturé au client, c'est une décision commerciale.

---

# Conformité légale

## Pages obligatoires

- **Mentions légales** — art. 6 LCEN. Certaines valeurs (SIRET, RCS, médiateur,
  adresse de l'hébergeur) ne peuvent venir que du client : elles sont laissées
  visiblement `[à compléter]` plutôt que remplies au hasard.
- **CGV** — art. L111-1 du code de la consommation. Elles décrivent **ce que le
  code fait réellement** : remise de 10 %, indemnité de 600 €, dix jours
  ouvrés, vingt-quatre heures de conservation, réponse sous 48 h, livraison
  comprise intra-muros. Un test vérifie que ces chiffres y figurent — un contrat
  qui annonce autre chose que le code serait faux.
- **Politique de confidentialité** — RGPD art. 13.

## Droit à l'effacement (art. 17 RGPD)

Le compte ne se supprime pas, il **s'anonymise**. Un `DELETE` emporterait les
commandes, que le code de commerce impose de conserver dix ans, ainsi que les
avis et le suivi.

`Utilisateur::anonymiser()` efface nom, prénom, e-mail, téléphone, adresse,
rôles et mot de passe, désactive le compte et annule toute réinitialisation en
cours. L'e-mail est remplacé par une valeur aléatoire en `@invalide.local`, de
sorte que deux effacements ne violent pas l'index unique.

## Pages d'erreur

404, 403 et 500 personnalisées. La 500 n'affiche **aucun détail technique** :
un message d'exception peut révéler la structure de la base ou des chemins de
fichiers.

---

# Statistiques (cahier des charges § 4 et § 5)

> « pour l'admin : […] voir les statistiques (commandes et chiffre d'affaires
> par menu) » — « une base MySQL […] et une base MongoDB pour les statistiques »

Deux comptes distincts, et la distinction n'est pas cosmétique :

- le **chiffre d'affaires** ne retient que les commandes **livrées**. Une
  commande en attente n'est pas un encaissement, une commande annulée ne le
  sera jamais.
- le **nombre de commandes** retient tout sauf les annulées : c'est l'activité,
  pas la recette.
- le **panier moyen** se rapporte aux seules commandes livrées. Diviser la
  recette par des commandes qui n'ont rien rapporté la tirerait artificiellement
  vers le bas.

Le calcul lit MySQL, seul dépositaire des commandes. MongoDB conserve les
relevés successifs : c'est l'accumulation dans le temps qu'une requête sur
MySQL ne saurait pas reconstituer après coup.

Les totaux affichés sont **cumulés depuis la première commande**, sans filtre
de date : chaque relevé est un cumul, et l'activité d'une période se lit dans
l'écart entre deux relevés.

Réservé à `ROLE_ADMIN`.

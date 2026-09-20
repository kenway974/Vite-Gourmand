# Configuration et réflexions initiales technologiques

Pour répondre aux besoins du traiteur Vite & Gourmand, le choix de l'architecture
doit concilier performance, sécurité et maintenabilité. La réflexion s'est portée
sur trois options offrant chacune avantages et inconvénients.

## Option 1 : Full Node.js / React (ou Next.js)

**Avantages** : écosystème 100 % JavaScript/TypeScript du front au back.
Réutilisation facile de composants React pour les parties dynamiques. Très
bonne fluidité globale pour l'utilisateur.

**Inconvénients** : l'écosystème JavaScript évolue très vite, ce qui entraîne
une instabilité relative sur le long terme. Cela demande une maintenance
continue.

## Option 2 : Back-end PHP avec Front-end React

**Avantages** : grande stabilité du back-end en PHP pour traiter les
formulaires, la logique de commande et la sécurité. Le front-end reste souple
et léger avec du React par exemple.

**Inconvénients** : gestion de deux couches distinctes à faire communiquer
(API REST ou injection de données dans les vues), ce qui peut complexifier le
workflow selon la techno choisie en front.

## Option 3 : Monolithe Full Symfony

**Avantages** : framework extrêmement stable, sécurisé et structuré
(architecture MVC). La gestion des rôles (Visiteur, Client, Employé, Admin),
la validation des formulaires et la modération des avis sont natives et très
simples à maintenir.

**Inconvénients** : moins de fluidité « sans rechargement de page » pour les
filtres complexes à moins d'y ajouter du JavaScript ciblé (Stimulus/UX ou
React embarqué).

## Choix de la base de données

Une approche hybride (SQL et NoSQL) est retenue pour répondre aux besoins :

- **Base relationnelle (PostgreSQL ou MySQL)** : indispensable pour gérer le
  cœur de l'application (clients, commandes, menus, plats et allergènes). Un
  SGBDR garantit l'intégrité des données liées et assure un calcul exact des
  tarifs.
- **Base NoSQL (MongoDB)** : utilisée en complément pour stocker les données
  plus souples ou indépendantes, comme l'historique des logs d'activité, la
  mise en cache ou les statistiques d'utilisation du site.

## Déploiement et hébergement

- **Architecture monolithique (PHP ou Node.js)** : le front-end et le
  back-end font partie du même projet. L'application est déployée sur un
  serveur unique (type Render, Railway ou VPS), ce qui simplifie la
  configuration et la gestion des déploiements.
- **Architecture découplée (API REST + Front React)** : le front-end et le
  back-end sont séparés. Le front-end est déployé sur un hébergeur optimisé
  pour les interfaces (type Vercel ou Netlify), tandis que l'API back-end et
  les bases de données sont hébergées sur un service dédié (type Render ou
  Railway).

---

## Choix final retenu

**Option 3 — Monolithe Full Symfony**, avec base hybride **MySQL + MongoDB**,
déployé sur **Railway**.

### Pourquoi cette option plutôt que les deux autres

- Le projet a quatre profils avec des droits imbriqués (Visiteur → Utilisateur
  → Employé → Admin) et plusieurs formulaires soumis à des règles métier
  strictes (calcul du prix, zones de livraison, modération des avis,
  effacement RGPD). C'est exactement le terrain où Symfony (sécurité par
  voters/rôles, validation par contraintes, formulaires liés aux entités)
  évite de réécrire à la main ce qu'un framework front-only ou une API séparée
  demanderait de faire deux fois (une fois côté API, une fois côté client).
- Le site n'a pas besoin d'interactivité temps réel poussée : c'est un
  catalogue avec filtres, un tunnel de commande et un espace de gestion. Le
  gain de fluidité d'un front découplé (Option 1 ou 2) ne justifiait pas la
  complexité supplémentaire (CORS, synchronisation des deux déploiements, API
  à versionner) pour un projet porté par une seule personne.
- Un monolithe se déploie en un seul service, ce qui correspond à la
  contrainte de délai du projet : moins de surface à maintenir et à
  débugger en solo qu'une architecture front/back séparée.

### Où l'hybride SQL/NoSQL a été utilisé concrètement

- **MySQL** porte tout ce qui a besoin d'intégrité relationnelle et de calculs
  exacts : utilisateurs, menus, plats, ingrédients, allergènes, commandes,
  zones de livraison, avis.
- **MongoDB** conserve l'historique des relevés statistiques (chiffre
  d'affaires, nombre de commandes, panier moyen), produits une fois par nuit
  par une commande dédiée. C'est un choix pragmatique : ce sont des
  photographies dans le temps, pas des données relationnelles, et MySQL seul
  ne permettrait pas de reconstituer l'évolution après coup sans les
  recalculer.
- Point de robustesse ajouté en cours de projet : si `MONGODB_URL` est absent
  ou l'extension indisponible, l'application démarre quand même (les totaux
  restent calculés depuis MySQL, seul l'historique est indisponible). Ça
  permet de développer et de lancer les tests sans dépendre d'un serveur
  NoSQL local.

### Hébergement retenu

**Railway**, en cohérence avec le cas « architecture monolithique » décrit
plus haut : un service PHP + une base MySQL managée + une base MongoDB
managée. Un second service (worker Symfony Messenger) est nécessaire en plus
du service web, pour l'envoi asynchrone des e-mails (réinitialisation de mot
de passe) — sans lui, les messages s'empilent en base sans jamais partir.

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

**Option 3 — Monolithe Full Symfony**, base hybride **MySQL + MongoDB**,
hébergement **Railway**.

- Symfony a été choisi car le projet a quatre profils aux droits imbriqués
  (Visiteur, Utilisateur, Employé, Admin) et des règles métier strictes
  (tarifs, livraison, modération des avis) : rôles, validation et
  formulaires sont natifs au framework, pas besoin de les recoder côté API
  et côté front comme l'auraient demandé les Options 1 et 2.
- Pas de besoin réel de temps réel/fluidité poussée (catalogue, tunnel de
  commande, back-office) : un monolithe suffit, et reste plus simple à
  gérer seul qu'une architecture front/back séparée.
- **MySQL** porte les données relationnelles (utilisateurs, menus, plats,
  commandes, avis…). **MongoDB** stocke l'historique des statistiques
  (CA, nombre de commandes, panier moyen), calculé chaque nuit — ce sont des
  photos dans le temps, pas des données relationnelles.
- **Railway** héberge le service PHP + les deux bases managées, plus un
  worker Messenger pour l'envoi asynchrone des e-mails.

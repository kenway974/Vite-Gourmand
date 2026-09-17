# Vite & Gourmand

Site de commande en ligne d'un traiteur bordelais.

Projet ECF — Titre professionnel Développeur Web et Web Mobile.

**Stack** : Symfony 7.4 · PHP 8.4 · Doctrine ORM 3 · Twig · MySQL 8

---

## Installation

### Prérequis

- PHP 8.4 avec les extensions `pdo_mysql`, `intl`, `mbstring`
- Composer
- MySQL 8 (ou MariaDB 10.11, la ligne est commentée dans `.env`)
- Symfony CLI (facultatif, pour le serveur de développement)

### Mise en route

```bash
composer install

# Option 1 — le conteneur fourni (MySQL 8, collation déjà réglée)
docker compose up -d

# Option 2 — un MySQL déjà installé
mysql -u root -p -e "CREATE DATABASE vite_gourmand CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Renseigner ses identifiants sans toucher au fichier versionné
cp .env .env.local
# puis éditer DATABASE_URL dans .env.local

php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

symfony server:start   # ou : php -S localhost:8000 -t public
```

`APP_SECRET` doit être renseigné dans `.env.local` :

```bash
php -r 'echo "APP_SECRET=" . bin2hex(random_bytes(16)) . PHP_EOL;' >> .env.local
```

### Collation

Les tables sont converties en `utf8mb4_unicode_ci` par une migration dédiée
(`Version20260917100000`). Ce n'est pas cosmétique : cette collation compare
sans tenir compte de la casse, ce qui fait que `Jean@example.fr` et
`jean@example.fr` restent le même identifiant. En collation binaire, l'index
unique sur l'e-mail laisserait passer deux comptes.

La recherche du catalogue, elle, ne dépend pas de ce réglage : elle porte sur
des colonnes normalisées en PHP, et se comporte donc à l'identique sur MySQL,
MariaDB ou SQLite.

### Courriels

La réinitialisation de mot de passe passe par Messenger : la réponse HTTP
n'attend pas le serveur SMTP. Un worker doit donc tourner en production.

```bash
php bin/console messenger:consume async
```

En développement, `MAILER_DSN=null://null` : rien n'est envoyé, et le
profileur Symfony montre les messages produits.

---

## Comptes de démonstration

Les fixtures créent six comptes partageant le mot de passe `Motdepasse&33!`.

| Rôle | Adresse |
|---|---|
| Administrateur | `admin@vite-gourmand.fr` |
| Employé | `employe@vite-gourmand.fr` |
| Client | `sophie.brunet@example.fr` |
| Client | `david.marchand@example.fr` |
| Client | `laetitia.fontaine@example.fr` |
| Compte désactivé | `compte.desactive@example.fr` |

Le dernier sert à vérifier qu'un compte désactivé ne peut ni se connecter, ni
recevoir de lien de réinitialisation.

Un administrateur peut aussi être créé en console, sans passer par les fixtures :

```bash
php bin/console app:creer-admin
```

---

## Tests

```bash
vendor/bin/phpunit
```

La suite tourne sur SQLite (`.env.test`) : aucun serveur à installer, base
recréée à chaque classe de test.

SQLite et MySQL ne se comportent pas à l'identique — SQLite rend `600` là où
MySQL rend `600.00` sur un `NUMERIC`, et son `LIKE` n'ignore la casse que sur
l'ASCII. Les tests en tiennent compte, mais une vérification sur MySQL avant
mise en production reste recommandée :

```bash
# Doctrine ajoute lui-même le suffixe « _test » au nom de la base
# (config/packages/doctrine.yaml), il ne faut donc PAS l'écrire ici.
DATABASE_URL="mysql://app:!ChangeMe!@127.0.0.1:3306/vite_gourmand?serverVersion=8.0.32&charset=utf8mb4" vendor/bin/phpunit
```

La base `vite_gourmand_test` doit exister et appartenir au même utilisateur :

```bash
mysql -u root -p -e "CREATE DATABASE vite_gourmand_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON vite_gourmand_test.* TO 'app'@'%';"
```

### Statistiques (MongoDB)

Le cahier des charges demande une base MySQL pour les données et **une base
MongoDB pour les statistiques**. Les relevés d'activité y sont conservés sous
forme de documents : chacun porte une ventilation par menu de taille variable,
et ajouter une métrique ne demandera aucune migration.

L'extension PHP est nécessaire :

```bash
sudo apt install php8.4-mongodb     # ou : pecl install mongodb
```

Les relevés sont produits par une commande, à faire tourner une fois par nuit :

```bash
php bin/console app:calculer-statistiques
```

Si `MONGODB_URL` est vide ou l'extension absente, l'application démarre quand
même : les chiffres du jour restent calculés depuis MySQL, seul l'historique
est indisponible. C'est ce qui permet de développer et de lancer les tests
sans serveur NoSQL.

---

## Déploiement

Cible : **Railway** — un service PHP et une base MySQL managée.

L'application a besoin de trois choses que le conteneur local fournit et qu'il
faut redéclarer sur l'hébergeur :

| Variable | Valeur |
|---|---|
| `APP_ENV` | `prod` |
| `APP_SECRET` | 32 caractères hexadécimaux, générés une fois |
| `DATABASE_URL` | l'URL MySQL fournie par Railway |
| `MAILER_DSN` | un vrai SMTP — sans lui, aucun courriel ne part |
| `COURRIEL_EXPEDITEUR` | l'adresse d'expédition |
| `MONGODB_URL` | l'URL MongoDB fournie par Railway |
| `MONGODB_BASE` | `vite_gourmand` |

Puis, dans l'ordre :

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:creer-admin
```

**Un second service est nécessaire** pour les courriels : la réinitialisation
de mot de passe passe par Messenger, la réponse HTTP n'attend donc pas le
serveur SMTP. Sans worker, les messages s'empilent en base et personne ne
reçoit rien.

```bash
php bin/console messenger:consume async
```

Les fixtures ne sont **pas** à charger en production : elles vident la base
avant de la remplir de données de démonstration.

---

## Documentation

- [`docs/regles-metier.md`](docs/regles-metier.md) — les règles métier relevées
  dans les maquettes, et leur traduction dans le code
- [`docs/charte-graphique/`](docs/charte-graphique/) — couleurs, typographies,
  contrastes RGAA

---

## Organisation du code

```
src/
├── Controller/        Admin/ et Employe/ pour les espaces protégés
├── Entity/            Le modèle, règles de validation comprises
├── Form/
├── Repository/        Les requêtes, y compris les jointures d'optimisation
├── Security/          Politique de mot de passe, réinitialisation, contrôle d'accès
├── Service/           CalculateurPrix — le cœur métier
├── Twig/
└── Validator/         Contraintes métier réutilisables
```

Les règles qui valent quel que soit le chemin d'entrée (site, console, future
API) vivent sur les entités, pas dans les contrôleurs : faisabilité d'une
commande, saisonnalité d'un menu, délai de restitution du matériel.

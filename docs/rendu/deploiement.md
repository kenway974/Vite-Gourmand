# Doc de déploiement

Déploiement en production sur **Railway**, à partir du dépôt Git
(https://github.com/kenway974/Vite-Gourmand, branche `develop`).

## Démarche

1. **Image Docker** : le projet embarque un `Dockerfile` basé sur le starter
   officiel Symfony pour **FrankenPHP** (PHP 8.3), avec les extensions
   nécessaires (`pdo_mysql` pour la base relationnelle, `mongodb` pour les
   statistiques). Railway construit cette image directement depuis le dépôt
   à chaque déploiement.
2. **Port** : Railway assigne le port via la variable d'environnement
   `PORT`. Le `Caddyfile` de FrankenPHP l'utilise directement (`:{$PORT:8080}`)
   plutôt que de dépendre de `SERVER_NAME`, dont la résolution via les
   références Railway s'est révélée peu fiable en pratique.
3. **Bases de données** : une base MySQL managée et une base MongoDB managée,
   toutes deux provisionnées sur Railway et reliées au service PHP par
   variables d'environnement.
4. **Variables d'environnement** à déclarer sur le service :

   | Variable | Valeur |
   |---|---|
   | `APP_ENV` | `prod` |
   | `APP_SECRET` | 32 caractères hexadécimaux, générés une fois |
   | `DATABASE_URL` | URL MySQL fournie par Railway |
   | `MAILER_DSN` | un vrai SMTP — sans lui, aucun courriel ne part |
   | `COURRIEL_EXPEDITEUR` | adresse d'expédition |
   | `MONGODB_URL` | URL MongoDB fournie par Railway |
   | `MONGODB_BASE` | `vite_gourmand` |

5. **Mise en route**, une fois le service démarré :

   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/console doctrine:migrations:migrate --no-interaction
   php bin/console app:creer-admin
   ```

   Les fixtures ne sont **pas** chargées en production : elles vident la
   base avant de la remplir de données de démonstration. Le catalogue de
   démonstration en production passe par une commande dédiée
   (`app:charger-catalogue-demo`), pas par `doctrine:fixtures:load`.

6. **Second service — worker Messenger**. La réinitialisation de mot de
   passe passe par Symfony Messenger : la réponse HTTP n'attend pas le
   serveur SMTP. Sans worker actif, les messages s'empilent en base et
   personne ne reçoit rien.

   ```bash
   php bin/console messenger:consume async
   ```

7. **Statistiques**. Le relevé nocturne (`php bin/console
   app:calculer-statistiques`) doit être planifié (cron Railway ou
   équivalent) pour alimenter l'historique MongoDB.

## Résultat

Site accessible en production :
https://vite-gourmand-production-c456.up.railway.app

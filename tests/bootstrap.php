<?php

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Le cache applicatif de l'environnement de test vit dans var/share/test
// (APP_SHARE_DIR) et survit d'un lancement à l'autre. Il contient l'état du
// login_throttling : sans cette purge, relancer la suite moins de 15 minutes
// après la précédente bloquerait les connexions des tests.
(new Filesystem())->remove(dirname(__DIR__).'/var/share/test');

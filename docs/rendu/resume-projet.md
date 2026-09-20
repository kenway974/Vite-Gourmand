# Résumé du projet (242 mots)

Vite & Gourmand est un traiteur bordelais dirigé par Julie et José depuis 25
ans. Ils envoyaient jusque-là leurs menus par e-mail, ce qui limitait leur
visibilité et compliquait la prise de commande. Le site développé remplace
cet usage par une vitrine publique et un espace de commande en ligne.

Quatre profils se partagent des droits croissants : le visiteur consulte le
catalogue sans commander ; l'utilisateur inscrit commande, suit ses commandes
et laisse un avis ; l'employé gère les menus, les plats, les horaires, les
commandes et modère les avis ; l'administrateur ajoute la création des
comptes employés, la gestion des zones de livraison et l'accès aux
statistiques.

Côté visiteur : accueil, catalogue filtrable et triable, fiche détaillée d'un
menu avec allergènes, page contact et « notre histoire ». Côté client
connecté : tunnel de commande avec calcul automatique du prix (tarif par
personne, remise de groupe, frais de livraison selon la zone), suivi de
statut et dépôt d'avis.

Techniquement, le back-end repose sur Symfony (PHP), avec MySQL pour les
données relationnelles et MongoDB pour l'historique des statistiques
administrateur. La sécurité s'appuie sur le hachage natif des mots de passe,
la protection CSRF et des rôles hiérarchisés ; l'accessibilité suit le RGAA.

Le site est déployé sur Railway, le code est sur un dépôt Git public, et le
RGPD est respecté : la suppression d'un compte l'anonymise plutôt que de
l'effacer, pour conserver l'historique de commandes imposé par le droit
commercial.

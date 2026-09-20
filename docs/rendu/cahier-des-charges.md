# Cahier des charges

## Contexte

Traiteur bordelais, 25 ans d'activité. Remplace l'envoi de menus par e-mail
par un site vitrine + commande en ligne.

## Objectifs

Présenter l'entreprise, afficher les menus (plats, prix, allergènes),
permettre la commande, offrir un espace de gestion.

## Profils et droits

Visiteur (consultation) → Utilisateur (+ commande, suivi) → Employé
(+ gestion menus/horaires/commandes, modération avis) → Admin (+ comptes
employés, zones de livraison, statistiques).

## Fonctionnalités

- **Public** : accueil, liste menus + filtres, détail menu, contact, notre
  histoire, avis, footer.
- **Client connecté** : inscription/connexion, commande, calcul auto du
  prix, suivi, avis.
- **Gestion** : CRUD menus/plats/ingrédients/allergènes, statuts commandes,
  modération avis, matériel prêté, (admin) comptes + zones + stats.

## Règles de tarification

- Prix par personne × nombre de convives.
- Remise de 10 % à partir de (minimum du menu + 5) convives.
- Livraison, dressage et reprise incluses à Bordeaux intra-muros ; supplément
  par zone hors de ce périmètre, code postal non couvert = commande refusée.
- Indemnité de 600 € si le matériel n'est pas restitué sous 10 jours ouvrés.
- Délai de commande minimum par menu, non négociable.

## Statistiques (admin)

Chiffre d'affaires, nombre de commandes et panier moyen (sur commandes
livrées uniquement), par menu, avec historique cumulé.

## Stack technique

Symfony (PHP) + MySQL (données relationnelles) + MongoDB (historique des
statistiques), déployé sur **Railway**.

## Tests (minimum)

Tests fonctionnels sur les parcours critiques (authentification, commande,
droits par profil) et tests unitaires sur les règles de tarification.

## Contraintes

Site en ligne à la date de rendu, dépôt Git public, RGPD, sécurité, RGAA.

## Liens

- Dépôt : https://github.com/kenway974/Vite-Gourmand
- Déploiement : https://vite-gourmand-production-c456.up.railway.app

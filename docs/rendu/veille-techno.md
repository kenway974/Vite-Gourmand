# Veille techno — vulnérabilités de sécurité

Pour connaître les failles les plus courantes, j'ai consulté le classement
**OWASP Top 10 2025**. Plusieurs catégories m'ont amené à des recherches et
décisions concrètes sur le projet :

- **Broken Access Control** (#1 du classement) → le contrôle d'accès par
  rôle est en place (`#[IsGranted('ROLE_...')]` sur les contrôleurs), et
  qu'un client ne voie que ses propres commandes est vérifié dans
  `CommandeController` (comparaison utilisateur/commande). La recherche sur
  les **Voters Symfony**, plus adaptés pour centraliser ce type
  d'autorisation fine, a été faite en amont ; leur implémentation est
  prévue en complément.
- **Cryptographic Failures** → recherche sur le meilleur algorithme de
  hachage des mots de passe (Argon2id vs bcrypt). Le projet utilise
  `algorithm: auto` (Symfony PasswordHasher), qui retient bcrypt en
  l'absence de l'extension sodium : ma recherche a confirmé que bcrypt,
  bien que plus ancien, reste très robuste.
- **Injection** → confirmation de l'importance des requêtes préparées.
  Toutes les requêtes passent par Doctrine ORM, qui les paramètre par
  défaut : pas de SQL construit à la main dans le projet.
- **Software Supply Chain Failures** → j'envisage d'utiliser
  `composer audit` régulièrement, pour vérifier les failles connues dans
  les dépendances (Symfony, Doctrine, mongodb).

Ce classement a servi à prioriser la sécurisation sur les failles les plus
fréquentes plutôt qu'au hasard.

# Recherche sur un site anglophone

## Situation de travail

Après avoir identifié le *Broken Access Control* comme faille prioritaire via
le classement OWASP, je suis allé chercher la solution technique côté
Symfony : la documentation officielle sur les **Voters**, le mécanisme natif
pour vérifier finement les permissions selon les rôles — en préparation de
leur implémentation, en complément des vérifications de rôle et de
propriétaire déjà en place.

L'objectif de cette lecture était de vérifier le fonctionnement du mécanisme
avant de l'implémenter, pour m'assurer de bien le câbler dans le projet — pas
de remettre en cause Symfony, qui est fiable et éprouvé, mais de comprendre
précisément son fonctionnement.

**Source** : Symfony, *"How to Use Voters to Check User Permissions"*,
Symfony Docs — https://symfony.com/doc/current/security/voters.html

## Extraits et traduction

**Extrait 1 (anglais)** :
> "Voters are Symfony's most powerful way of managing permissions. They
> allow you to centralize all permission logic, then reuse them in many
> places."

**Traduction** :
> « Les voters sont le moyen le plus puissant de Symfony pour gérer les
> permissions. Ils permettent de centraliser toute la logique de permission,
> puis de la réutiliser à plusieurs endroits. »

**Extrait 2 (anglais)** :
> "Here's how Symfony works with voters: All voters are called each time you
> use the isGranted() method on Symfony's authorization checker or call
> denyAccessUnlessGranted() in a controller [...] Ultimately, Symfony takes
> the responses from all voters and makes the final decision (to allow or
> deny access to the resource) according to the strategy defined in the
> application, which can be: affirmative, consensus, unanimous or priority."

**Traduction** :
> « Voici comment Symfony fonctionne avec les voters : tous les voters sont
> appelés à chaque fois que la méthode isGranted() est utilisée sur le
> vérificateur d'autorisation de Symfony, ou que denyAccessUnlessGranted()
> est appelée dans un contrôleur [...] Au final, Symfony prend en compte les
> réponses de tous les voters et prend la décision finale (autoriser ou
> refuser l'accès à la ressource) selon la stratégie définie dans
> l'application, qui peut être : affirmative, consensus, unanime ou par
> priorité. »

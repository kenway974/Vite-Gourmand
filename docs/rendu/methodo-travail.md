# Explication de ma gestion de projet

Pour le développement du site Vite & Gourmand, j'ai mis en place une
organisation Agile personnalisée via un tableau Kanban sur Notion, adaptée à
mon emploi du temps de salarié.

**Suivi sur Trello** : utilisation d'un Kanban classique (À faire, En cours,
Terminé) pour avoir une vision claire de l'avancement à tout moment et
supprimer la charge mentale due à l'incertitude avant chaque session de
travail (permet aussi de rester focus sur ce que j'ai à faire plutôt que le
résultat final).

**Fonctionnement en micro-sprints** : dès que j'ai un créneau disponible
(ex. 1h), je me fixe un nombre minimal de tâches très précises à accomplir.
Si une tâche n'est pas terminée à la fin du créneau, je note exactement ce
qu'il reste à faire dans la carte Notion. Cela me permet de reprendre
immédiatement lors de la session suivante sans perte de temps ni hésitation.
Grâce à cette approche, je m'assure d'avancer régulièrement malgré mon emploi
salarié fixe.

**Approche centrée-conception** : j'ai commencé par toute la phase de
conception (charte graphique, MCD, diagrammes) en utilisant l'IA Claude comme
partenaire de réflexion pour recevoir un maximum d'idées et explorer
plusieurs pistes, tout en ne conservant que les éléments strictement
nécessaires afin d'éviter la sur-complexification ou certaines incohérences
dont l'IA pourrait faire preuve. Résoudre la logique métier et l'architecture
en amont permet de créer une sorte de moule dans lequel le développement
viendra se couler naturellement et de manière fluide.

## Organisation Git

Le fichier s'intitule « Git — explication méthodo travail », donc pour être
complet sur ce point précis : le suivi Notion/Trello pilote le *quoi*, Git
pilote le *comment* c'est intégré au code. Concrètement sur ce dépôt :

- **Une branche par fonctionnalité** (`zone-livraison`, `mot-de-passe-oublie`,
  `espace-compte`, `retour-materiel`, `catalogue-recherche`,
  `statistiques-mongodb`, `conformite-legale`, `droits-employe`,
  `pages-publiques`…), fusionnée dans `develop` via une pull request plutôt
  qu'en direct. Ça isole chaque sujet, et ça donne un historique lisible par
  thème plutôt qu'un flux continu de commits mélangés.
- **Un commit = une intention lisible**, rédigé à l'impératif en français et
  décrivant ce que le commit change plutôt que « fix » ou « update » :
  par exemple *« Ajoute les zones de livraison et leur supplément »* ou
  *« Filtre les convives par paliers plutôt qu'en saisie libre »*. L'historique
  sert de documentation de second niveau, utile en le relisant plusieurs
  semaines après.
- **Relecture systématique après implémentation** : plusieurs branches
  contiennent un commit dédié du type *« Colmate cinq défauts trouvés en
  relisant les pages publiques »*. Après avoir codé une fonctionnalité, je
  reprends le diff à froid avant de la fusionner, ce qui rattrape des bugs
  qu'on ne voit pas en écrivant le code au fil de l'eau.
- **Dépôt public** dès le début du projet, comme l'impose le cahier des
  charges — ce qui pousse à des messages de commit propres et à ne jamais
  laisser de secret ou de mot de passe en dur dans le code versionné.

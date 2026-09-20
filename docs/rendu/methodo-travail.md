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

- Une branche par fonctionnalité (`zone-livraison`, `espace-compte`,
  `statistiques-mongodb`…), fusionnée dans `develop` par pull request plutôt
  qu'en direct.
- Commits à l'impératif, décrivant le changement plutôt que « fix » ou
  « update » (ex. *« Ajoute les zones de livraison et leur supplément »*).
- Relecture du diff avant fusion, avec un commit dédié si des défauts sont
  trouvés (ex. *« Colmate cinq défauts trouvés en relisant les pages
  publiques »*).
- Dépôt public dès le début, comme l'impose le cahier des charges.

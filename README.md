# I-AMU_Web_APP

## Détails du projet : 
Réalisation d'un plugin *Moodle* qui sera ajouté à *Ametice*. Il prendra la forme d'un lien ***hypertext*** dans chaque matiere où il est disponible, ce lien ouvrira un second onglet dans lequel une interface de *chat* sera accessible pour discuter avec le ***models***.

Ce plugin permettra : 
-  L'accès à des ***models*** d'IA qui auront connaissance des cours d'une matière.
- Proposera une environnement d'apprentissage encadré par des **règles sur le models** ainsi que sur les **nombres de tokens disponibles**.
- Permettra aux professeurs d'avoir accès aux échanges entre le model et ses étudiants de sorte à pouvoir analyser la facon dont les élèves utilisent l'IA.


## Exploitation du produit

Ce produit est pensé pour fonctionner en symbiose avec la plateforme ***Ametice/Moddle***. Par conséquence la partie connexion y est totalement déléguée.

L'environnement de l'établissement (*Moodle*) dispose, en fonction de son forfait, d'un **nombre fixe de token par mois**. Chacun des cours se partagera ce nombre de token pour finalement les diviser équitablement entre les étudiants. En conséquence, les étudiants tout comme les professeurs apprendrons à mieux utiliser leurs tokens.

Les professeurs pourront aussi créer des sessions dans lesquels un nombre de token disponibles sera renseigné afin d'autoriser plus ou moins l'utilisation de l'outil en fonction du cours (ex : TD, TP, exam).

---

## Infrastructure

Lors du dévellopement de ce projet, **trois** serveurs seront utilisés. Un serveur de ***Dev*** pour tester en direct l'avancé du projet, un de ***preProd*** qui permet une validation de la fonction par tous, et enfin un de ***Prod*** qui comme son nom l'indique est la version courante et officielle du produit.

Le projet est constitué de deux entité distinctes. La première est le **LMS** (*Learning Management System*) ametice, il nous sert à organiser les modèles par cours et par règles du professeur.<br>
La seconde est **le serveur IA**, comme son nom l'indique il héberge l'IA et est donc en charge des calcules des réponse.

Le plugin s'affichera dans le **LMS** et pointera les messages vers le **serveur IA** puis enverra la question et la réponse vers une **base de données** hébergée sur le même serveur que celui du **LMS** et dans des fichiers de log temporaires.


--- 

## Reflexions personnelles 
Le TUTOR AI existe déjà ? Si oui c'est notre projet de l'intégrer dans une application web et de rajouter les interfaces et son emplacement des les cours moodle.

Qui a dev ce plugin, voir avec lucas. Il est deployer sur le serveur de lucas. 

Voir le serveur de dev sur lesquels sont deployé ses plugins pour voir ce qui existe déjà.


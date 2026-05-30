# I-AMU_Web_APP

## Détails du projet : 
Réalisation d'un plugin *Moodle* qui sera ajouté à *Ametice*. Il prendra la forme d'un lien ***hypertext*** dans chaque matiere où il est disponible, ce lien ouvrira un second onglet dans lequel une interface de *chat* sera accessible pour discuter avec le ***models***.

Ce plugin permettra : 
-  L'accès à des ***models*** d'IA qui auront connaissance des cours d'une matière.
- Proposera une environnement d'apprentissage encadré par des **règles sur le models** ainsi que sur les **nombres de tokens disponibles**.
- Permettra aux professeurs d'avoir accès aux échanges entre le model et ses étudiants de sorte à pouvoir analyser la facon dont les élèves utilisent l'IA.

---

## Tests et qualité du code

Outils installés en `require-dev` dans `app/composer.json` et exécutés
automatiquement en CI (GitHub Actions) à chaque `push` sur toute
branche et à chaque pull request.

> **Note sur Composer** : le runtime utilise un autoloader maison
> (`app/autoload.php`) chargé par `public/index.php`. Composer n'est
> utilisé que pour installer les outils de qualité (PHPStan, PHPCS,
> PHPUnit) — `vendor/autoload.php` ne sert qu'à ces outils lors de
> l'analyse statique et des tests. Les deux mappings PSR-4 (maison et
> Composer) sont volontairement identiques pour éviter une dérive
> entre ce que voient les outils et ce qui tourne en production.

### Installation des dépendances de dev

```bash
cd app
composer install
```

### Commandes disponibles (à lancer depuis `app/`)

| Commande           | Outil                  | Rôle                                                         |
|--------------------|------------------------|--------------------------------------------------------------|
| `composer lint`    | `php -l`               | Vérifie la syntaxe PHP de tous les fichiers `src/`, `public/`, `tests/`. |
| `composer stan`    | PHPStan (niveau 6)     | Analyse statique : types, méthodes inexistantes, code mort. |
| `composer cs`      | PHP_CodeSniffer        | Vérifie le respect de la norme PSR-12.                       |
| `composer cbf`     | PHP Code Beautifier    | Corrige automatiquement le style PSR-12 (ce qui est auto-corrigeable). |
| `composer test`    | PHPUnit 10             | Lance les tests des dossiers `tests/Unit` et `tests/Integration`. |
| `composer quality` | lint + stan + cs       | Enchaîne les trois validations principales. À lancer avant de pousser. |

### Exemples d'utilisation

Vérification complète avant un commit :

```bash
cd app
composer quality
```

Correction automatique du style :

```bash
cd app
composer cbf
```

Lancer uniquement les tests :

```bash
cd app
composer test
```

### Intégration continue (GitHub Actions)

Le workflow `.github/workflows/ci.yml` exécute à chaque push trois
jobs en parallèle :

- **PHP quality** : `lint`, `phpstan`, `phpcs` (informationnel
  pendant la phase de transition), `phpunit`.
- **Infrastructure** : validation de la syntaxe de
  `docker-compose.yaml`.
- **Schéma SQL** : rejoue `database/schema.sql` puis
  `database/seed.sql` sur un PostgreSQL 17 fraîchement instancié,
  pour détecter toute régression SQL.

Les erreurs PHPStan déjà présentes lors de l'introduction de l'outil
sont consignées dans `app/phpstan-baseline.neon` : le CI ne bloque
que sur les **nouvelles** erreurs. Après avoir corrigé des erreurs,
on peut rétrécir cette baseline :

```bash
cd app
composer stan -- --generate-baseline phpstan-baseline.neon
```

# Fonctionnalité : Intégration et Gestion des Modèles LLM

Cette fonctionnalité permet de communiquer de manière agnostique avec différents modèles d'Intelligence Artificielle (comme Ollama, OpenAI, etc.). L'architecture repose sur le **Design Pattern Adapter**, permettant d'ajouter de nouveaux modèles ou fournisseurs d'API simplement en base de données, sans modifier le cœur de l'application.

---

## Présentation et Données

Actuellement, seul le modèle `llama3.2:1b` est disponible et configuré. 

Les informations de chaque modèle sont entièrement pilotées par la base de données. On y stocke notamment :
* Le nom du modèle (`name`)
* La fenêtre de contexte (`infoContextWindow`)
* La taille du modèle (`infoSizeOfModel`)
* L'entreprise émettrice (`infoCompany`)
* L'URL de l'API cible (`url`)
* Le type d'adaptateur à utiliser (`adapter_type`)

---

## Architecture et Flux d'Exécution

Lorsqu'une demande de chat est émise, le flux suit les étapes suivantes :

1. **Routage :** La requête HTTP `POST` arrive sur le `LLMController`.
2. **Vérification (Repository) :** Le contrôleur appelle `AiRepository` pour vérifier si le modèle demandé existe en base de données et récupère ses configurations.
3. **Instanciation (Métier) :** Le contrôleur instancie l'entité `ModelAi` (ou `AI`) avec ses données spécifiques.
4. **Adaptation (Pattern Adapter) :** En fonction de la colonne `adapter_type` récupérée en BDD, l'application utilise une classe spécifique implémentant l'interface `LlmAdapterInterface`. Cet adaptateur se charge de traduire fidèlement la requête au format attendu par l'API cible (ex: format spécifique pour Ollama).
5. **Exécution :** La requête formatée est transmise via cURL au conteneur ou serveur respectif, et la réponse brute est retournée.

---

## Utilisation (Exemple de Requête)

Tu peux tester l'endpoint de l'application en envoyant du JSON brut via une commande `curl` dans ton terminal :

```bash
curl -X POST http://localhost:8085/chat \
  -H "Content-Type: application/json" \
  -d '{
    "model" : "llama3.2:1b",
    "message" : "Présente toi",
    "context" : []
  }'
```
Si vous avez déjà utilisé l'application, il se peut que vous aillez besoin de racharger les images des conteneur docker. Pour ce faire faite : 
```bash
docker compose up --build -d
```

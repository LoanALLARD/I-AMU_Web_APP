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

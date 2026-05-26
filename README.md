# I-AMU 🎓🤖

**Application web de traçabilité d'usage de LLM pour l'enseignement**

I-AMU permet aux étudiants d'accéder à des LLM locaux dans un cadre pédagogique encadré, tout en permettant aux enseignants de superviser les usages et aux chercheurs d'analyser les données collectées.

---

## Prérequis

- **PHP** >= 8.1
- **PostgreSQL** >= 14
- **Composer** (gestionnaire de dépendances PHP)
- **Ollama** (pour l'hébergement local des LLM)

## Installation

### 1. Cloner le projet

```bash
git clone <url-du-repo> i-amu
cd i-amu
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env.example .env
# Éditer le fichier .env avec vos paramètres
```

### 4. Créer la base de données

```bash
# Créer la base et l'utilisateur PostgreSQL
sudo -u postgres psql -c "CREATE USER iamu_user WITH PASSWORD 'iamu_password';"
sudo -u postgres psql -c "CREATE DATABASE iamu OWNER iamu_user;"

# Exécuter le schéma
psql -U iamu_user -d iamu -f database/schema.sql

# Charger les données initiales
psql -U iamu_user -d iamu -f database/seed.sql
```

### 5. Installer Ollama et télécharger un modèle

```bash
# Installer Ollama (voir https://ollama.ai)
curl -fsSL https://ollama.ai/install.sh | sh

# Télécharger un modèle
ollama pull mistral
ollama pull llama3
```

### 6. Lancer le serveur de développement

```bash
# Depuis le dossier du projet
php -S localhost:8080 -t public/
```

L'application est accessible sur **http://localhost:8080**

---

## Structure du projet

```
i-amu/
├── app/
│   ├── config/          # Configuration (routes, config globale, langues)
│   ├── controllers/     # Contrôleurs MVC
│   ├── core/            # Classes fondamentales (Application, Router, Database, Controller, Model)
│   ├── models/          # Modèles de données
│   ├── services/        # Services métier (AuthService, OllamaService)
│   └── views/           # Vues (templates PHP)
│       ├── layout/      # Layouts (main.php)
│       └── pages/       # Pages par section
├── database/            # Scripts SQL (schema, seed)
├── public/              # Point d'entrée web (index.php, assets)
│   └── assets/
│       ├── css/
│       ├── js/
│       └── img/
├── tests/               # Tests unitaires (PHPUnit)
├── composer.json
├── phpstan.neon
└── .env.example
```

## Rôles utilisateurs

| Rôle | Attribution | Permissions principales |
|------|------------|----------------------|
| **Étudiant** | Auto (email @etu.univ-amu.fr) | Chat libre, rejoindre cours/examens |
| **Enseignant** | Auto (email @univ-amu.fr) | Créer sessions, superviser, pré-prompting |
| **Enseignant Spécialisé** | Admin uniquement | + Importer des modèles personnalisés |
| **Chercheur** | Admin uniquement | Export données JSON, dashboards analytiques |
| **Admin** | Manuel | Gestion complète, configuration, rôles |

## Compte admin par défaut

- **Email :** admin@univ-amu.fr
- **Mot de passe :** admin123
- ⚠️ **Changer le mot de passe immédiatement en production**

## Commandes utiles

```bash
# Lancer les tests
composer test

# Analyse statique du code
composer analyse

# Vérifier la connexion Ollama
curl http://localhost:11434/api/tags
```

## Conformité RGPD

L'application intègre un système de consentement obligatoire. Sans acceptation, l'accès à la plateforme est bloqué. Les données peuvent être exportées et supprimées sur demande.

---

*Projet SAE - BUT Informatique - Aix-Marseille Université*

## Nouveautés UX (v2)

L'application intègre désormais :
- **Streaming des réponses** : les réponses du LLM s'affichent mot par mot (Server-Sent Events)
- **Rendu Markdown** : les réponses sont formatées (titres, listes, gras) avec coloration syntaxique du code et bouton "copier"
- **Indicateur de statut Ollama** : pastille verte/rouge en temps réel dans la barre de navigation
- **Mode sombre** : bouton 🌙/☀️ dans la navbar (préférence sauvegardée localement)
- **Page d'accueil** : landing page avant connexion
- **Favicon** généré depuis le logo

### Note sur le streaming

Le serveur PHP intégré (`php -S`) gère le streaming, mais pour de meilleures performances en production, utilisez **Apache** ou **Nginx**. Si le streaming semble bufferisé, vérifiez que `output_buffering = Off` dans votre `php.ini`.

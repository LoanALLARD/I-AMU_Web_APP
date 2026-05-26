# Stack Docker - I-AMU V2-6

## Services

| Service   | Port  | URL                            | Rôle                              |
|-----------|-------|--------------------------------|-----------------------------------|
| `app`     | 8080  | http://localhost:8080          | Application PHP + Apache          |
| `db`      | 5433  | localhost:5433 (hôte)          | PostgreSQL 14 (5432 en interne)   |
| `pgadmin` | 8081  | http://localhost:8081          | pgAdmin 4 (admin PostgreSQL officiel) |

> **Ollama** tourne nativement sur l'hôte (`localhost:11434`). L'app dans Docker y accède via `host.docker.internal:11434`.

## Démarrage

```bash
# Depuis le dossier i-amuV2-6/
docker compose up -d --build

# Installer les dépendances Composer (1ʳᵉ fois)
docker compose exec app composer install

# Suivre les logs
docker compose logs -f app
```

L'application est dispo sur **http://localhost:8080**.
Admin par défaut : `admin@univ-amu.fr` / `admin123`.

## Connexion à pgAdmin

URL : http://localhost:8081

**Login pgAdmin** (interface) :
- Email : `admin@iamu.fr`
- Mot de passe : `admin`

Une fois connecté, le serveur **"I-AMU PostgreSQL"** est déjà enregistré dans la sidebar (sous `Servers`) avec le mot de passe pré-rempli — un clic dessus connecte directement à la base `iamu`.

## Ollama : télécharger un modèle (sur l'hôte)

Ollama tourne en natif, pas dans Docker. Les commandes s'exécutent directement :

```bash
ollama pull mistral
ollama pull llama3

# Vérifier
curl http://localhost:11434/api/tags
```

## Commandes utiles

```bash
# Lancer les tests
docker compose exec app composer test

# Analyse statique
docker compose exec app composer analyse

# Recharger le schéma SQL (⚠️ efface les données)
docker compose down -v
docker compose up -d

# Shell dans l'app
docker compose exec app bash

# Shell PostgreSQL
docker compose exec db psql -U iamu_user -d iamu
```

## Notes

- Le code est monté en volume (`./:/var/www/html`), donc toutes les modifications PHP sont prises en compte immédiatement.
- Le schéma et le seed sont chargés automatiquement au **premier** démarrage (volume `db-data` vide). Pour rejouer, faire `docker compose down -v`.
- Pour le GPU NVIDIA avec Ollama, décommenter le bloc `deploy:` dans `docker-compose.yml`.

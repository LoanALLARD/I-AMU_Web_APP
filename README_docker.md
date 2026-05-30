# Infrastructure
3 conteneurs : 
- **ollama**, il héberge les modeles. Utile lors du dev
- **db**, héberge la base de données.
- **PHP**, héberge l'application web.

Pour les lancer on utilise docker composer. 
Se placer au même niveau que le fichier `docker-compose.yaml`, puis faire la commande suivante.
```bash
$ docker compose up -d
```
Vérifiez que les conteneurs ce sont bien lancé avec `docker ps `

---

### API Ollama

Envoyer une requête à un model avec l'API.<br>
exemple sur **Linux**:
```bash
$ curl http://localhost:8082/api/generate -d '{
  "model": "llama3.2:1b",
  "prompt": "raconte moi une histoire",
  "stream": false,
  "format":"json"
  }'
``` 
exemple **Windows**:
```powershell
(Invoke-WebRequest -method POST -Body '{"model":"llama3.2:1b", "prompt":"dis moi bonjour", "stream": false}' -uri http://localhost:11434/api/generate ).Content | ConvertFrom-json
```

`modal` : nom du modèle <br>
`prompt` : contenue de la demande <br>
`stream` : *false* réponse en un seul block, *true* réponse token par token.<br> 
`format`: format de la réponse. <br>
`context`: historique de la conversation en token des réponses précedentes.<br>
  exemple `"context":[128006,9125,128007,271,38766,1303,33025,2696,25,6790,220,2366,18,271,128009,128006,882]`

---

### Base de données

Version : **17-alpine**

Le conteneur possède deux volumes, un premier *db_data* pour sauvegarder les données. Le second un *bind mount* `init-scripts` dans lequel se trouve le script de creation des tables de la base de donnée.

Il existe aussi un cinquième conteneur qui contient l'interface web vers la base de données. Pour y accèder cliquez [ici](http://localhost:8083/)

---

### PHP
Version : **8.3**

Le contenue de `/var/www/html` du conteneur est monté en *bind mount* sur votre répertoire `app` de votre machine. Ce qui implique que tout changement dans `app` change en direct la configuration dans le serveur php. 

Une fois le serveur lancer, cliquer [ici](http://localhost:8085/) pour voir votre page web.



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
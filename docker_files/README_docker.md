# Infrastructure
3 conteneurs : 
- **ollama**, il héberge les modeles. Utile lors du dev
- **db**, héberge la base de données.
- **PHP**, héberge l'application web.

Pour les lancer on utilise docker composer. 
Se placer dans la racine du projet, puis faire la commande suivante.
```bash
$ docker compose up -d
```
Vérifiez que les conteneurs ce sont bien lancé avec `docker ps `

---

### API Ollama

Envoyer une requête à un model avec l'API.<br>
exemple :
```bash
$ curl http://localhost:11434/api/generate -d '{
  "model": "llama3.2:1b",
  "prompt": "raconte moi une histoire",
  "stream": false,
  "format":"json",
  "context":[128006,9125,128007,271,38766,1303,33025,2696,25,6790,220,2366,18,271,128009,128006,882]
  }'
``` 
`modal` : nom du modèle <br>
`prompt` : contenue de la demande <br>
`stream` : *false* réponse en un seul block, *true* réponse token par token.<br> 
`format`: format de la réponse. <br>
`context`: historique de la conversation en token des réponses précedentes. <br>

---

### Base de données


---

### PHP
Faire l'application au global. Fork le *poc* du projet d'alexendre  
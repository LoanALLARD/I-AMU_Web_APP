# SPEC: documents-rag — Gestion de fichiers & début de RAG (sessions + chat)

> Date: 2026-06-07 | Branche: `dev` | Statut: **proposée (non implémentée)**
> Origine: décline [`02-sessions.md §11.1`](./02-sessions.md) (« Upload de
> documents par l'enseignant », niveaux *léger* vs *RAG*) en un plan concret.

## Contexte

Le rapport mentionne le *« chargement de document »* dans les besoins enseignant
(cadrage du modèle). On veut un **système de gestion de fichiers** qui sert de
**socle à un RAG** (Retrieval-Augmented Generation), livré **progressivement**
pour rester utilisable à chaque étape :

1. **Documents de session** — l'enseignant joint des fichiers à une session ;
   les étudiants inscrits peuvent les **consulter**. *(Gestion de fichiers pure,
   aucune IA.)*
2. **Import dans le chat** — un étudiant/utilisateur joint un document à une
   conversation et son **contenu est pris en compte** dans la réponse de l'IA.
   *(Injection « légère » du texte extrait.)*
3. **RAG de session** — les documents joints à la session **cadrent réellement
   l'IA** : indexation (chunks + embeddings), recherche sémantique au moment du
   prompt, injection des passages pertinents. *(RAG complet.)*

> Chaque phase est **autonome et livrable**. La phase 1 ne dépend d'aucune IA ;
> la phase 2 réutilise l'extraction de texte de la phase 1 ; la phase 3 ajoute
> l'indexation vectorielle par-dessus le stockage de la phase 1.

## Vue d'ensemble

| Phase | Périmètre | IA ? | Nouveauté lourde |
|---|---|---|---|
| **1** | Joindre des docs à une session, consultables par les étudiants | ❌ | Stockage fichiers + extraction texte |
| **2** | Joindre un doc dans le chat, pris en compte par l'IA | ✅ (léger) | Injection du texte dans le prompt `system` |
| **3** | Docs de session utilisés pour cadrer l'IA (RAG) | ✅ (RAG) | Chunking + embeddings + recherche vectorielle |

## Décisions à valider (⚠️ avant implémentation)

| Sujet | Recommandation (défaut) | Alternatives |
|---|---|---|
| **Types de fichiers acceptés** ✅ **validé** | **PDF, Markdown, TXT** uniquement | DOCX écarté pour l'instant (nécessiterait une lib supplémentaire) |
| **Taille max / fichier** | 10 Mo | à ajuster selon l'hébergement |
| **Nombre max de docs / session** | 20 | — |
| **Emplacement de stockage** | disque, `storage/documents/` **hors `public/`**, servi par un contrôleur authentifié | objet S3-like (hors périmètre POC) |
| **Extraction PDF→texte** | binaire `pdftotext` (poppler-utils) dans l'image PHP | lib PHP `smalot/pdfparser` (pure PHP, plus lente) |
| **Recherche RAG (phase 3)** | `pgvector` (image `pgvector/pgvector:pg17`) + cosine | fallback : embeddings en `float4[]` + cosine en SQL/PHP (petits corpus) ; ou full-text `tsvector` (sans embeddings) |
| **Modèle d'embeddings** | Ollama `nomic-embed-text` (à puller) | `mxbai-embed-large` |
| **RGPD** | un doc peut contenir des données perso → suppression en cascade, durée de rétention alignée sur la session | journalisation `data_access_log` (cf. spec 06) |

> **Important** : `pgvector` **n'est pas** dans l'image Postgres actuelle
> (`SELECT … pg_available_extensions WHERE name='vector'` → vide). La phase 3
> impose donc soit de **changer l'image** (`pgvector/pgvector:pg17`), soit le
> **fallback** ci-dessus. À trancher au lancement de la phase 3.

---

## Stockage des fichiers (transverse, posé en phase 1)

- Fichiers **sur disque**, dans `storage/documents/{session_<id>|conversation_<id>}/{uuid}.{ext}`,
  monté en volume docker — **jamais sous `public/`** (pas d'URL directe).
- La **BD ne stocke que les métadonnées** + le **texte extrait** ; jamais le
  binaire.
- Téléchargement/affichage **toujours via un contrôleur** qui vérifie les droits
  puis `readfile()` avec les bons en-têtes (`Content-Type`, `Content-Disposition`).
- **Schéma d'URL** — symétrique au scope du document, toujours servi par le
  contrôleur (jamais en statique vers `storage/`) :
  - document de **session** : `GET /documents/session_{sessionId}/{docId}` ✅ (phase 1)
  - document de **conversation** : `GET /documents/conversation_{conversationId}/{docId}` (phase 2)

  Le `{docId}` (et non le nom) identifie le fichier — les noms ne sont **pas
  uniques** dans un scope ; le nom réel est restitué via `Content-Disposition`.
  Le contrôleur vérifie que le document appartient bien au scope nommé dans
  l'URL (un docId d'un autre scope via ce chemin → 403).
- Nom de fichier stocké = **UUID** (jamais le nom d'origine sur le disque) ; le
  nom d'origine est conservé en BD pour l'affichage. Évite traversée de chemin et
  collisions.

### Base de données (migration commune)

```sql
CREATE TYPE document_status_type AS ENUM ('PENDING', 'READY', 'FAILED');

CREATE TABLE documents (
    id BIGSERIAL,
    session_id BIGINT,                 -- doc de session (phase 1/3) ; NULL si doc de chat
    conversation_id BIGINT,            -- doc de chat (phase 2) ; NULL si doc de session
    uploaded_by_id BIGINT NOT NULL,    -- users.id
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL, -- chemin relatif sous storage/
    mime_type VARCHAR(100) NOT NULL,
    size_bytes INTEGER NOT NULL,
    extracted_text TEXT,               -- rempli après extraction (phase 1)
    status document_status_type NOT NULL DEFAULT 'PENDING',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT pk_documents PRIMARY KEY (id),
    CONSTRAINT fk_documents_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE RESTRICT,
    CONSTRAINT ck_documents_scope CHECK (
        (session_id IS NOT NULL AND conversation_id IS NULL) OR
        (session_id IS NULL AND conversation_id IS NOT NULL)
    ),
    CONSTRAINT ck_documents_size CHECK (size_bytes > 0)
);
CREATE INDEX idx_documents_session ON documents (session_id);
CREATE INDEX idx_documents_conversation ON documents (conversation_id);
```

> Un document appartient à **une session OU une conversation** (`ck_documents_scope`).
> `ON DELETE CASCADE` : supprimer la session/conversation supprime les métadonnées
> (le `DocumentService` supprime les fichiers disque correspondants — voir RGPD).

---

## Phase 1 — Documents de session (gestion de fichiers, sans IA)

### Objectifs
Permettre à l'enseignant **propriétaire** d'une session de **joindre, lister et
supprimer** des documents, et aux **étudiants inscrits** de les **consulter /
télécharger**.

### User stories
- En tant qu'**enseignant**, je veux joindre un PDF de consignes à ma session
  pour que mes étudiants y aient accès au même endroit que le chat.
- En tant qu'**étudiant inscrit**, je veux consulter les documents de la session
  pour préparer/suivre la séance.
- En tant qu'**enseignant**, je veux retirer un document obsolète.

### Domaine
- **`Document`** (entity) : `id`, `sessionId?`, `conversationId?`, `uploadedById`,
  `originalName`, `storedPath`, `mimeType`, `sizeBytes`, `extractedText?`,
  `status: DocumentStatus`, `createdAt`. Invariants : scope session **xor**
  conversation ; `sizeBytes > 0`.
- **`DocumentStatus`** (enum string) : `Pending`/`Ready`/`Failed` (état de
  l'extraction de texte). Labels FR + badge, comme `SessionStatus`.

### Application (Services)
- **`DocumentService::attachToSession(int $sessionId, int $userId, array $uploadedFile): Document`**
  — valide (type MIME réel via `finfo`, taille, quota), déplace le fichier
  (`move_uploaded_file` → `storage/…/{uuid}.ext`), insère la ligne (`PENDING`),
  déclenche l'extraction de texte, passe `READY`/`FAILED`. Lève
  `DocumentException` (type non supporté, trop gros, quota atteint).
- **`DocumentService::listForSession(int $sessionId): list<Document>`**
- **`DocumentService::delete(int $documentId, int $userId): void`** — vérifie la
  propriété (enseignant owner), supprime le fichier disque **puis** la ligne.
- **`DocumentService::openForDownload(int $documentId, int $userId): array`** —
  vérifie l'accès (owner **ou** étudiant inscrit), renvoie `[path, name, mime]`.
- **Extraction de texte** — `TextExtractorInterface` (port) +
  `PdfTextExtractor` / `PlainTextExtractor`. Sélection par MIME. Le texte extrait
  est stocké (`documents.extracted_text`) **dès la phase 1** (réutilisé en
  phases 2 et 3).

### Infrastructure (Models)
- **`DocumentRepository`** : `insert`, `findById`, `listBySession`,
  `listByConversation`, `updateStatusAndText`, `delete`.

### HTTP
```
POST   /sessions/{id}/documents          → DocumentController::uploadToSession   (enseignant owner)
GET    /sessions/{id}/documents          → (intégré au dashboard, pas de page dédiée)
GET    /documents/{docId}                → DocumentController::download           (owner OU étudiant inscrit)
POST   /documents/{docId}/delete         → DocumentController::delete             (enseignant owner)
```
- `DocumentController` : `multipart/form-data`, lit `$_FILES` via le helper
  `input()` (ou un helper `file()` à ajouter), CSRF obligatoire.

### Vue
- **Section « Documents » sur le dashboard de session** (`session/dashboard.php`) :
  zone d'upload (drag & drop + `<input type=file>`) côté enseignant, liste des
  docs (nom, taille, type, date) avec bouton **Télécharger** pour tous et
  **Supprimer** pour l'owner.
- **Côté étudiant** : la même liste, lecture seule, accessible dans
  l'environnement chat de la session (panneau latéral ou onglet).

### Sécurité (phase 1)
- Validation **MIME réelle** (`finfo_file`), **pas** l'extension ni le
  `Content-Type` client.
- Nom disque = UUID ; **jamais** le nom utilisateur (anti path-traversal).
- Téléchargement gaté par contrôleur (owner / étudiant inscrit) — **jamais** de
  lien direct vers `storage/`.
- Quota par session + taille max appliqués côté service.

### Tests (phase 1)
| Niveau | Cible | Exemples |
|---|---|---|
| Unit Domain | `Document` / `DocumentStatus` | invariant scope xor, labels |
| Unit Application | `DocumentService` (extracteur mocké) | refus type/taille/quota, propriété au delete |
| Integration | `DocumentRepository` (PDO réel) | insert/list/delete |
| Acceptance | routes HTTP | upload owner OK, upload non-owner 403, download étudiant inscrit OK / non-inscrit 403 |

---

## Phase 2 — Import de documents dans le chat (prise en compte « légère »)

### Objectifs
Un utilisateur joint un document **à sa conversation** ; son **texte extrait**
est **injecté dans le contexte** envoyé au modèle, sans indexation vectorielle.

### User stories
- En tant qu'**étudiant**, je veux glisser un PDF dans le chat et poser des
  questions dessus, pour que l'IA réponde en s'appuyant sur ce document.

### Application
- Réutilise **`DocumentService::attach…`** avec scope **conversation**
  (`conversation_id`) et l'extraction de texte de la phase 1.
- **Construction du contexte** — au moment de l'appel LLM (`LLMController` /
  `ChatService`), on récupère les docs de la conversation et on **préfixe** le
  `system` (l'actuel `$preprompt` d'`OllamaAdaptater::generate(..., $preprompt)`) :

  ```
  [pre_prompt_override de la session]

  Documents fournis par l'utilisateur (extraits) :
  --- {nom_doc_1} ---
  {extracted_text tronqué à N tokens}
  --- {nom_doc_2} ---
  …
  ```
- **Garde-fou de taille** : le texte injecté est **borné** (ex. `max_input_size`
  de la session, ou un plafond global) pour ne pas dépasser la fenêtre de
  contexte du modèle. Au-delà → troncature + mention « (document tronqué) ».
  *(C'est précisément la limite qui motive la phase 3 : au lieu de tout injecter,
  on ne récupère que les passages pertinents.)*

### HTTP / Vue
```
POST /chat/{conversationId}/documents → DocumentController::uploadToConversation
```
- **Vue chat** (`pages/home.php`) : bouton trombone dans la barre de saisie →
  upload → puce « document joint » au-dessus du composer ; les docs joints sont
  listés et supprimables.

### Sécurité / RGPD (phase 2)
- L'utilisateur ne peut joindre/voir que **ses** docs de conversation.
- En **examen** (`SessionType::Exam`), l'import par l'étudiant est **désactivé**
  (cohérent avec le mode « pas d'historique / surveillance »).

### Tests (phase 2)
- `DocumentService` scope conversation ; construction du contexte (préfixe
  `system`, troncature au plafond) ; refus d'upload en session `EXAM`.

---

## Phase 3 — RAG : cadrer réellement l'IA avec les documents de session

### Objectifs
Les documents **de session** (phase 1) ne sont plus injectés en bloc : ils sont
**découpés, vectorisés et indexés** ; à chaque prompt, on **recherche les
passages les plus proches** de la question et on **n'injecte que ceux-là** dans
le `system`. C'est le vrai « cadrage » de l'IA.

### Pipeline
1. **Chunking** — à l'upload (phase 1), le `extracted_text` est découpé en
   passages (~500–800 tokens, chevauchement ~10 %).
2. **Embeddings** — chaque chunk est vectorisé via Ollama
   (`POST {ollama}/api/embeddings`, modèle `nomic-embed-text`). Stockage du
   vecteur.
3. **Recherche** — au prompt, on vectorise la **question**, puis on récupère les
   `k` chunks les plus proches (cosine) **scopés à la session**.
4. **Injection** — les `k` passages sont mis dans le `system` (même mécanisme que
   phase 2) avec leur source ; le modèle répond « cadré ».

### Domaine / Infra
- **`DocumentChunk`** (entity) : `id`, `documentId`, `chunkIndex`, `content`,
  `embedding`, `tokenCount`.
- **`EmbeddingAdaptaterInterface`** (port, à côté de `LlmAdaptaterInterface`) +
  **`OllamaEmbeddingAdaptater`** : `embed(string $text): array` (liste de floats).
- **`RagService`** : `indexDocument(Document)`, `retrieve(int $sessionId, string $query, int $k): list<DocumentChunk>`.
- **`DocumentChunkRepository`** : `insertMany`, `searchNearest(sessionId, queryEmbedding, k)`.

### Base de données (migration phase 3)
**Option recommandée — `pgvector`** (image `pgvector/pgvector:pg17`) :
```sql
CREATE EXTENSION IF NOT EXISTS vector;

CREATE TABLE document_chunks (
    id BIGSERIAL,
    document_id BIGINT NOT NULL,
    chunk_index INTEGER NOT NULL,
    content TEXT NOT NULL,
    embedding vector(768),            -- dim. selon le modèle (nomic-embed-text = 768)
    token_count INTEGER,
    CONSTRAINT pk_document_chunks PRIMARY KEY (id),
    CONSTRAINT fk_document_chunks_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE
);
CREATE INDEX idx_document_chunks_embedding ON document_chunks
    USING hnsw (embedding vector_cosine_ops);
```
Recherche : `ORDER BY embedding <=> :query_embedding LIMIT :k` (join sur
`documents.session_id = :sid`).

**Fallback sans pgvector** : `embedding float4[]` (ou JSONB) + cosine calculé en
SQL ou en PHP. Acceptable pour de petits corpus de session, sans index ANN.

### Intégration LLM
- `ChatService` construit le `system` final =
  `pre_prompt_override` **+** passages RAG (phase 3) **+** docs de chat (phase 2).
  Un seul point d'injection : le paramètre `system` d'`OllamaAdaptater`.
- **Post-prompt** (`post_prompt_override`) reste appliqué tel quel après.

### Dépendances techniques (phase 3)
- Image Postgres avec `pgvector` **ou** fallback float[].
- Modèle d'embeddings Ollama pullé (`nomic-embed-text`).
- (Indexation asynchrone idéale, mais un traitement **synchrone à l'upload**
  suffit pour le POC.)

### Tests (phase 3)
- Chunking déterministe (taille/chevauchement) ; `RagService::retrieve` renvoie
  les bons chunks (embeddings mockés) ; intégration `searchNearest` (pgvector ou
  fallback) ; le `system` contient bien les passages récupérés.

---

## Sécurité & RGPD (transverse)

- **Accès** : doc de session → owner + étudiants inscrits ; doc de chat → son
  auteur. Toujours gaté côté contrôleur.
- **Suppression** : supprimer une session/conversation **cascade** sur
  `documents` (BD) ; le `DocumentService` supprime les **fichiers disque** et les
  **chunks/embeddings** associés (droit à l'effacement).
- **Données perso** : un document peut contenir des données personnelles →
  rentre dans le périmètre [spec 06 RGPD](./06-rgpd.md). À terme : journaliser
  l'accès/export dans `data_access_log` (dette commune avec l'export).
- **Examen** : pas d'import étudiant en session `EXAM`.

## Réutilisation POC
- `git show poc:app/...` — vérifier si le POC contient une ébauche d'upload
  (sinon, page blanche assumée).
- ⚠️ À ne PAS copier : tout stockage de fichier **dans `public/`**, toute
  validation par **extension** plutôt que MIME réel.

## Anti-patterns à éviter
- ❌ Servir les fichiers par URL directe (`public/uploads/...`) — toujours via
  contrôleur authentifié.
- ❌ Faire confiance au `Content-Type`/nom envoyés par le client.
- ❌ Stocker le binaire en base.
- ❌ Phase 3 : tout réinjecter en bloc (c'est la phase 2) — le RAG **sélectionne**.
- ❌ Bloquer l'upload sur l'indexation : l'extraction/indexation peut échouer
  (`status = FAILED`) sans perdre le fichier.
- ❌ Confondre **doc de session** (cadrage pédagogique, partagé) et **doc de
  chat** (contexte privé d'une conversation).

## Découpage en commits (indicatif)
1. `feat(documents): add documents table + storage + Document domain` (migration, entity, repo).
2. `feat(documents): attach/list/download/delete on session dashboard` (phase 1).
3. `feat(documents): text extraction (pdf/txt/md)` (phase 1, extracteurs).
4. `feat(chat): import documents into a conversation` (phase 2 upload + UI).
5. `feat(chat): inject conversation documents into the system prompt` (phase 2 intégration LLM).
6. `feat(rag): chunk + embed session documents` (phase 3 indexation).
7. `feat(rag): retrieve relevant chunks and frame the model` (phase 3 recherche + injection).

## Fichiers (prévisionnels)
- `database/schema/01_schema.sql` (+ migration) — `documents`, `document_chunks`, enums.
- `src/Domain/Document.php`, `src/Domain/DocumentStatus.php`, `src/Domain/DocumentChunk.php`, `src/Domain/DocumentException.php`.
- `src/Domain/TextExtractorInterface.php` + `PdfTextExtractor` / `PlainTextExtractor`.
- `src/Domain/EmbeddingAdaptaterInterface.php` + `OllamaEmbeddingAdaptater.php` (phase 3).
- `src/Models/DocumentRepository.php`, `src/Models/DocumentChunkRepository.php`.
- `src/Services/DocumentService.php`, `src/Services/RagService.php` (phase 3).
- `src/Controllers/DocumentController.php`.
- `src/Views/pages/session/dashboard.php` (section docs), `src/Views/pages/home.php` (trombone chat).
- `public/index.php` (routes), `docker-compose.yml` (volume `storage/`, image pgvector phase 3).

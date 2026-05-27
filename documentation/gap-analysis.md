# Gap analysis — Rapport préliminaire vs Specs

> **Date** : 2026-05-27
> **Référence rapport** : `Rapport_Propre.pdf` (24 pages, daté du début
> de projet, équipe Acemyan / Allard / Atherly / Jacob / Valette).
> **Référence specs** : `specs/00-foundations.md` à `specs/06-rgpd.md`.
>
> Ce document recense les **points qui n'ont pas été traités directement**
> par les corrections de Niveau 1 (déjà appliquées dans les specs). Il
> sert de checkpoint pour la prochaine réunion client et de mémo des
> features could-have à anticiper.

---

## Niveau 2 — Clarifications à demander au client

Ces points sont **ambigus** dans le rapport. Avant de coder, on doit
trancher avec le client (M. Flouvat).

### Q1. Code d'accès examen — un par classe ou un par étudiant ?

**Citation rapport (§2.3.1)** :
> *« L'interface devra permettre à un enseignant de créer un cours et
> de générer un code pour que les étudiants puissent rejoindre ce
> cours. Il pourra également créer un examen, générer un* ***code à
> usage unique*** *pour que les étudiants le rejoignent. »*

**Interprétations possibles** :

| Lecture | Implication technique |
|---|---|
| « Usage unique » = valide uniquement pour cette session | **POC actuel** : un seul `AccessCode` par session, partagé. |
| « Usage unique » = un code différent pour chaque étudiant | Nouvelle table `session_invite (session_id, code, student_id?, used_at)`. L'étudiant échange son code unique contre une `Conversation` au join. |

**Recommandation tech** : option 1 (plus simple, fonctionne en POC,
distribuer N codes différents impose un workflow d'envoi par mail aux
étudiants ou un export à imprimer).

**À demander** : « Doit-on générer **un code partagé** ou **N codes
individuels** pour un examen ? »

---

### Q2. Multi-rôles sur un même compte ou comptes séparés ?

**Citation rapport (§2.1.6)** :
> *« John M. est un enseignant d'informatique et un administrateur de
> l'application […]. Il possède donc* ***deux comptes****, un compte
> administrateur et un compte enseignant spécialisé, ce qui lui permet
> d'assumer ses deux rôles. »*

**Interprétations** :

| Lecture | Implication technique |
|---|---|
| Multi-rôles autorisés sur un compte unique | **POC actuel** : un user peut être student+teacher+admin simultanément. Choix simple, conforme aux tables `student/teacher/researcher/administrator`. |
| Comptes séparés obligatoires | Empêcher l'attribution de + d'un rôle "privilégié" (`teacher_specialised`, `researcher`, `admin`) à un même user. Plus restrictif, oblige le user à se déconnecter pour changer de chapeau. |

**Recommandation tech** : option 1 (POC actuel). Le persona John dit
"deux comptes" mais c'est probablement une description de SON usage
personnel, pas une règle imposée par le système.

**À demander** : « Le multi-rôles sur un compte est-il acceptable, ou
faut-il imposer la séparation ? »

---

### Q3. Upload de documents — concaténation simple ou RAG ?

**Citations rapport** :
- §2.3.1 (besoins fonctionnels enseignant) : *« L'enseignant pourra
  appliquer des contraintes sur le modèle telles que le prépromting, le
  temps d'utilisation, la taille du prompt,* ***le chargement de
  document*** *et le post-prompting. »*
- §2.3.3 (could-have) : *« la possibilité d'envoyer des documents aux
  LLM »*

**Interprétations** :

| Lecture | Implication technique |
|---|---|
| Document → concaténé au pré-prompt | Simple : champ `attached_document_text` ou upload de fichier converti en texte (PDF → txt). Aucune dépendance externe. |
| Document → RAG (embedding + recherche sémantique) | Lourd : vector store (Qdrant / pgvector), embeddings côté Ollama, retrieval à chaque prompt. Sort du périmètre d'un projet étudiant. |

**Recommandation tech** : option 1 pour le could-have. La contrainte
puissance de calcul (§2.4) rend RAG difficile.

**À demander** : « Si on implémente l'upload de documents, c'est un
texte concaténé au pré-prompt ou un vrai RAG ? »

---

### Q4. Anonymisation côté UI dashboard chercheur

Le rapport (§2.3.2) est très clair :
> *« aucune anonymisation n'est faite par notre plateforme. Celle-ci
> est de la responsabilité de la personne traitant les données par la
> suite. »*

Mais le mockup du dashboard chercheur (capture envoyée) montre un
**toggle "anonymisé"** dans la barre de filtres.

**À clarifier** :

| Comportement attendu | Implication |
|---|---|
| Toggle UI cosmétique (masque les noms à l'écran) | Reste compatible avec la règle "pas d'anonymisation plateforme". Export reste avec noms. |
| Toggle qui anonymise *aussi* l'export | Contredit la règle du rapport. À écarter. |
| Toggle absent | Plus simple, on ne montre jamais les noms dans le dashboard, ils restent dans l'export. |

**Recommandation tech** : toggle cosmétique (option 1), explicité dans
spec 06 §1.

**À demander** : « Le toggle anonymisé dans le dashboard doit-il aussi
toucher l'export ? »

---

### Q5. Durée d'archivage — propre à l'utilisateur ou globale ?

**Citation rapport (§2.3.1)** :
> *« Tous les utilisateurs auront accès à une page de gestion du compte
> où ils pourront modifier la durée d'archivage des conversations et
> le thème de l'application. »*

→ Implique **durée par utilisateur**. Notre spec 01 l'a ajouté
(`user.conversation_archive_days`), mais la **valeur par défaut** et la
**borne max** sont à fixer.

**À demander** :
- Valeur par défaut ? (Suggestion : 180 jours, héritée de
  `config.rgpd.conversation_archive_days` actuel)
- Borne maximale ? (Suggestion : 1095 jours = 3 ans, alignée sur la
  durée de conservation RGPD globale)
- Que se passe-t-il une fois la durée écoulée ? Suppression définitive
  ou simple `is_archived = true` ?

---

## Niveau 3 — Should-have / could-have à intégrer plus tard

Pas urgent, mais à ne pas oublier. Ces features sont mentionnées dans
le rapport mais ne figurent pas (ou seulement en aparté) dans les specs
actuelles.

### N3.1 Import LLM personnalisés par Enseignant Spécialisé

**Rapport** : personas Susan (§2.1.3), Hamish (§2.1.2),
contexte §2.2.3, **principal motif de l'existence du rôle
Enseignant Spécialisé**.

**État specs** : aucune spec ne couvre l'import. La spec 05 mentionne
seulement la sync depuis Ollama (qui ne pull que les modèles déjà
téléchargés sur le serveur).

**Ce qu'il faut** :
- Endpoint `POST /admin/models/import` (rôle `teacher_specialised` ou
  `admin` requis).
- Champ "Tag Ollama à pull" + déclenchement de `ollama pull <tag>` via
  l'API Ollama (`POST /api/pull`).
- Polling pour suivre l'avancement (réponse streamée).
- À la fin, sync auto pour faire apparaître le modèle dans la table
  `model`.

**Priorité suggérée** : Should-have. À intégrer dans une future
section de la spec 05 ou nouvelle `spec-07-model-import.md`.

---

### N3.2 Mode examen — retrait d'un étudiant en cours

**Citation rapport (§2.3.1)** :
> *« afin d'avoir à nouveau accès aux autres modes, l'enseignant devra
> mettre fin à l'examen,* ***supprimer un étudiant de l'examen*** *ou
> le temps imparti à l'examen devra arriver à son terme. »*

**État specs** : spec 04 couvre l'archive globale (`archiveSession`) et
le flag d'un prompt, mais **pas le retrait individuel** d'un étudiant
de l'examen.

**Ce qu'il faut** :
- Service `RemoveStudentFromExamService(int $conversationId, int $teacherId)`.
- Endpoint `POST /exam/{sessionId}/remove-student/{conversationId}`.
- Effet : `conversation.is_archived = true`, `submitted_at = NOW`, et
  côté client (mode examen verrouillé) un polling JS qui détecte la
  fermeture et déverrouille l'UI.

**Priorité suggérée** : Should-have. À intégrer dans spec 04.

---

### N3.3 Verrouillage UI mode examen

**Citation rapport (§2.3.1)** :
> *« L'interface utilisateur changera en mode examen afin de faciliter
> la surveillance des postes durant les examens. L'étudiant ne pourra
> pas quitter le mode examen. »*

**État specs** : le POC a déjà un `app/views/layout/exam.php` séparé du
layout principal (pas de navbar, juste un compte à rebours). Mais ce
n'est pas formellement décrit dans les specs.

**Ce qu'il faut** :
- Section dédiée dans spec 04 : "Layout examen verrouillé" qui explicite :
  - Navbar retirée (juste logo + timer + nom utilisateur).
  - JavaScript de détection `beforeunload` qui avertit avant fermeture
    d'onglet.
  - Mode plein écran (`document.documentElement.requestFullscreen()`)
    suggéré au lancement.
  - Pas de bouton "déconnexion" accessible.
- À noter : un vrai verrouillage est impossible côté navigateur (l'user
  peut toujours fermer son onglet). On limite par surveillance visuelle
  + journalisation.

**Priorité suggérée** : Should-have. À intégrer dans spec 04.

---

### N3.4 Vérification IP en examen

**Citation rapport (§2.3.3, could-have)** :
> *« vérification des IP lors des examens pour limiter les possibilités
> de triche »*

**État specs** : non couvert.

**Ce qu'il faut** :
- À la jonction d'une session examen, enregistrer l'IP de l'étudiant
  dans `conversation.join_ip`.
- À chaque prompt, vérifier que l'IP courante == `join_ip`. Si différent,
  alerte côté supervision (badge "IP changée" sur la ligne étudiant).
- Pas de blocage automatique (un changement d'IP peut être légitime :
  changement de réseau Wi-Fi). Juste un signal pour l'enseignant.

**Priorité suggérée** : Could-have. Migration `ALTER TABLE conversation
ADD COLUMN join_ip VARCHAR(45)`. Section à ajouter à spec 04.

---

### N3.5 Multi-modèle dans une même conversation

**Citation rapport (§3.1, choix architectural)** :
> *« nous pouvons donc au sein d'une même conversation s'adresser à
> plusieurs modèles afin de profiter de leurs points forts respectifs. »*

**État specs** : techniquement possible (chaque `Interaction.model_id`
est indépendant), mais **non explicité** dans la spec 03.

**Ce qu'il faut** :
- Ajouter une section dans spec 03 §3 (Entities) qui clarifie que le
  `model_id` est porté par `Interaction`, pas par `Conversation`.
- UI : ajouter un dropdown de changement de modèle dans la zone de chat
  (existe déjà en POC).
- ViewModel : `ConversationDetailView` doit lister les modèles utilisés
  dans la conversation (statistique : "3 modèles utilisés sur 12 tours").

**Priorité suggérée** : à clarifier en spec 03 (gratuit, déjà supporté).
Pas de migration nécessaire.

---

### N3.6 Mention d'information CNIL — texte définitif

**Citation rapport (§5.2)** : modèle CNIL fourni avec placeholders
(`[identité du responsable]`, `[finalités]`, `[base légale]`, etc.).

**État specs** : spec 06 prévoit le placeholder mais le **texte final**
reste à rédiger avec la direction AMU / le DPO universitaire.

**À demander** :
- Identité légale du responsable de traitement (Aix-Marseille
  Université ? Direction de la Recherche ? IUT ?).
- Adresse de contact du DPO.
- Base légale exacte (intérêt légitime ? mission de service public ?).
- Durée précise de conservation.

**Priorité suggérée** : doit être fixé avant la mise en production,
mais peut être en placeholder pour le dev.

---

### N3.7 Journalisation : durée de conservation et purge

**État specs** : spec 06 décrit la table `data_access_log` (append-only,
3 ans minimum) mais ne couvre pas la **purge automatique** au-delà.

**À prévoir** :
- Tâche cron mensuelle : `DELETE FROM data_access_log WHERE at < NOW() - INTERVAL '3 years'`.
- Endpoint admin pour purger manuellement avant date (exceptionnel).

**Priorité suggérée** : Could-have. À ajouter à spec 06 §10 "Évolutions".

---

## Récapitulatif — actions clés

| Action | Avant prochaine étape | Acteur |
|---|---|---|
| Trancher Q1 (code examen partagé vs unique) | Réunion client | M. Flouvat |
| Trancher Q2 (multi-rôles vs comptes séparés) | Réunion client | M. Flouvat |
| Trancher Q3 (upload doc : simple vs RAG) | Réunion client | M. Flouvat |
| Trancher Q4 (toggle anonymisation) | Réunion client | M. Flouvat |
| Fixer défauts Q5 (durée archivage) | Réunion client | M. Flouvat |
| Récupérer texte CNIL définitif (N3.6) | Avant mise en prod | DPO AMU |
| Implémenter N3.1 → N3.7 | Après specs 00-06 livrées | Équipe projet |

---

## Suivi

Mettre à jour ce document à chaque réunion client : déplacer les
questions résolues vers une section "Décisions" en bas, et tenir
**les specs concernées synchronisées**.

### Décisions prises

*(Vide pour l'instant — sera complété au fur et à mesure des
réunions.)*

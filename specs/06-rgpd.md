# Spec 06 — RGPD

## 0. Statut
- **Priorité** : must-have (obligation légale)
- **Dépend de** : 00-foundations, 01-auth-account
- **État POC** : partiel (consentement bloquant OK, mais ni mention d'information,
  ni 4 droits formalisés, ni journalisation)
- **Référence externe** : [Guide RGPD du développeur — CNIL](https://www.cnil.fr/fr/guide-rgpd-du-developpeur),
  rapport d'analyse préliminaire §5.

## 1. Objectifs

Mettre la plateforme **en conformité RGPD** sur ses 4 axes :

1. **Mention d'information** affichée à l'inscription et au consentement,
   listant le responsable de traitement, la finalité (recherche
   pédagogique), la base légale, la durée de conservation, les droits.
2. **Sécurisation** : authentification forte, principe du moindre
   privilège (les rôles donnent ce qu'il faut, pas plus), journalisation
   des accès sensibles.
3. **4 droits utilisateur** (information, accès, rectification /
   effacement / limitation, opposition) implémentés via endpoints
   dédiés et accessibles depuis la page Mon compte.
4. **Conservation** : durée paramétrable par utilisateur (cf. spec 01)
   et politique globale lisible dans la configuration.

> **Précision capitale** (rapport §2.3.2) : *« aucune anonymisation
> n'est faite par notre plateforme. Celle-ci est de la responsabilité
> de la personne traitant les données par la suite. »* Tous les exports
> chercheurs incluent nom, prénom, n° étudiant. Le toggle "anonymisé"
> dans l'UI dashboard chercheur (spec 05) est **purement cosmétique**
> (masque les noms à l'écran, ne modifie pas l'export).

## 2. Personas RGPD

- **Tout utilisateur** : doit pouvoir consulter la mention d'info,
  refuser le consentement (et être bloqué), exporter ses données,
  rectifier son profil, supprimer son compte, s'opposer à figurer dans
  les corpus de recherche.
- **Admin** : doit pouvoir consulter le **journal d'accès** aux données
  (qui a téléchargé quoi, quand).
- **Chercheur** : doit voir ses propres exports tracés (auditabilité).

## 3. Domaine

### Value Objects — `App\Domain\ValueObjects`

```php
final class GdprConsent {
    public function __construct(
        public readonly bool $granted,
        public readonly ?DateTimeImmutable $grantedAt,
        public readonly ?DateTimeImmutable $revokedAt,
        public readonly bool $researchOpposed,    // droit d'opposition recherche
    ) {}

    public function grant(DateTimeImmutable $now): self;
    public function revoke(DateTimeImmutable $now): self;
    public function opposeResearch(): self;
    public function acceptResearch(): self;
}
```

### Entity — `App\Domain\Entities\DataAccessLog`

Journalise les actions sensibles (export de corpus, accès aux données
d'un autre utilisateur, modification de rôle).

```php
final class DataAccessLog {
    public function __construct(
        private readonly int $id,
        private readonly int $actorUserId,        // qui a fait l'action
        private readonly string $action,          // 'export_corpus', 'view_user_data', 'role_change', …
        private readonly ?int $targetUserId,       // sur qui (si applicable)
        private readonly array $context,           // payload JSON (filtres, etc.)
        private readonly string $ipAddress,
        private readonly DateTimeImmutable $at,
    ) {}
}
```

### Interfaces

```php
interface DataAccessLogRepositoryInterface {
    public function append(DataAccessLog $entry): void;
    /** @return list<DataAccessLog> */
    public function findForUser(int $userId, int $limit = 100): array;
    /** @return list<DataAccessLog> */
    public function findRecent(int $limit = 200): array;
}
```

## 4. Application (use-cases)

| Service | Méthode | Droit RGPD |
|---|---|---|
| `ShowPrivacyNoticeService` | `execute(): PrivacyNoticeView` | Information |
| `ExportMyDataService` | `execute(int $userId): string` (JSON) | Accès |
| `UpdateProfileService` (existe en spec 01) | — | Rectification |
| `DeleteAccountService` (existe en spec 01) | — | Effacement |
| `OpposeResearchUseService` | `execute(int $userId)` | Opposition |
| `AcceptResearchUseService` | `execute(int $userId)` | (réversible) |
| `LogDataAccessService` | `execute(int $actorId, string $action, ?int $targetId, array $ctx)` | Sécurisation |

### Politique de journalisation

Actions **obligatoirement** journalisées :

| Action | `action` (constante) | Trigger |
|---|---|---|
| Export de corpus chercheur | `export_corpus` | `ExportResearchCorpusService` |
| Consultation conversation d'un autre user | `view_user_conversation` | Supervision (spec 04) |
| Changement de rôle | `role_change` | `AttachRoleToUserService` / `DetachRoleFromUserService` |
| Suppression de compte | `account_deleted` | `DeleteAccountService` |
| Reset de mot de passe par admin | `password_reset_by_admin` | (futur) |

> Le journal est en **append-only**, pas de suppression. Conservation 3
> ans minimum (cf. §5.1.2 du rapport).

## 5. Infrastructure

- `PdoDataAccessLogRepository` (insert simple, queries par user/date).
- `LogDataAccessService` injecté dans **tous** les services qui touchent
  aux droits d'un autre utilisateur ou aux exports.

## 6. HTTP

### Routes

```
GET   /privacy                       PrivacyController::notice
GET   /gdpr/consent                  GdprController::show
POST  /gdpr/consent                  GdprController::handle
GET   /account/data-export           AccountController::exportMyData      # droit d'accès
POST  /account/oppose-research       AccountController::opposeResearch    # droit d'opposition
POST  /account/accept-research       AccountController::acceptResearch
GET   /admin/data-access-log         AdminController::dataAccessLog       # journal admin
```

### Views

- `legal/privacy.php` — page **publique** avec la mention d'information CNIL.
  Accessible sans connexion (lien dans le footer).
- `auth/gdpr_consent.php` — interception bloquante au login si pas de consentement.
- Section "Consentement recherche" de `/account` (déjà prévue en spec 01) :
  ajouter un toggle **"Je m'oppose à figurer dans les corpus de recherche"**.

### Templates de la mention d'information

Cf. rapport §5.2 (texte CNIL adapté). Stocker en `app/Views/legal/privacy.php`
avec les placeholders remplacés par la config (`config.rgpd.controller`,
`config.rgpd.dpo_contact`, etc.).

## 7. Base de données

### Migration

```sql
-- database/migrations/AAAA-MM-DD-rgpd-compliance.sql
BEGIN;

-- Opposition spécifique à la finalité recherche (séparé du consentement global)
ALTER TABLE "user"
    ADD COLUMN IF NOT EXISTS research_opposed BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS gdpr_consent_revoked_at TIMESTAMP;

-- Journal d'accès aux données
CREATE TABLE IF NOT EXISTS data_access_log (
    id              SERIAL PRIMARY KEY,
    actor_user_id   INT NOT NULL REFERENCES "user"(user_id) ON DELETE SET NULL,
    action          VARCHAR(64) NOT NULL,
    target_user_id  INT REFERENCES "user"(user_id) ON DELETE SET NULL,
    context         JSONB,
    ip_address      VARCHAR(45) NOT NULL,
    at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_data_access_log_actor ON data_access_log (actor_user_id, at DESC);
CREATE INDEX idx_data_access_log_action ON data_access_log (action, at DESC);

COMMIT;
```

### Config à exposer

Dans `app/config/config.php`, section `rgpd` :

```php
'rgpd' => [
    'controller_name'         => 'Aix-Marseille Université',
    'controller_contact'      => 'dpo@univ-amu.fr',
    'legal_basis'             => 'Intérêt légitime (recherche scientifique)',
    'retention_days_default'  => 1095,        // 3 ans
    'require_consent'         => true,        // bloquant si refus
],
```

## 8. Filtres d'opposition à la recherche

Dans `BuildResearchCorpusService` et `ExportResearchCorpusService`
(spec 05) :

```php
// Filtre obligatoire ajouté à TOUTES les requêtes corpus
WHERE u.research_opposed = FALSE
  AND u.gdpr_consent = TRUE
```

> Un utilisateur qui s'est opposé à figurer dans la recherche **n'apparaît
> plus** dans les exports et les agrégats. Les conversations sont
> conservées (droits d'accès propre toujours valide), mais ignorées par
> la couche chercheur.

## 9. Réutilisation POC

| Fichier POC | Action |
|---|---|
| `app/controllers/GdprController.php` | Reprendre la logique de consentement bloquant. À étendre avec `opposeResearch` / `acceptResearch`. |
| `app/views/pages/auth/gdpr_consent.php` | Réutilisable, à enrichir avec un lien vers `/privacy`. |
| `app/views/pages/account/index.php` | Section "Consentement recherche" déjà présente, à étendre avec le toggle d'opposition. |

## 10. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Domain | `GdprConsent::revoke` | Renvoie une instance avec `granted=false, revokedAt=now`. |
| Unit Application | `ExportMyDataService` | Inclut conversations + interactions + sessions du user dans le JSON. |
| Unit Application | `OpposeResearchUseService` | Bascule le flag, log l'événement. |
| Integration | Filtre `WHERE research_opposed = FALSE` dans `PdoResearchRepository` | Un user opposé n'apparaît pas dans `byCourse`. |
| Acceptance | `GET /privacy` sans authent | HTML 200 avec la mention CNIL. |
| Acceptance | `GET /account/data-export` connecté | Content-Type JSON, contient les données du user. |

## 11. Anti-patterns spécifiques

- ❌ Anonymiser les données **côté plateforme**. Le rapport est explicite :
  c'est la responsabilité du chercheur en aval.
- ❌ Supprimer une ligne du journal `data_access_log`. Append-only.
- ❌ Réimplémenter la mention d'information dans plusieurs vues.
  Centraliser dans `Views/legal/privacy.php` et y renvoyer.
- ❌ Logger des **contenus** de prompts dans `data_access_log.context`.
  Seulement des métadonnées (qui, quoi, quand, sur qui, IP).
- ❌ Confondre **droit d'effacement** (suppression compte) et **droit
  d'opposition** (figurer dans la recherche). Deux endpoints distincts.

---

## 12. Mapping des 4 droits

| Droit RGPD | Endpoint | Service | Vue |
|---|---|---|---|
| **Information** | `GET /privacy` + `GET /gdpr/consent` | `ShowPrivacyNoticeService` | `legal/privacy.php` + `auth/gdpr_consent.php` |
| **Accès** | `GET /account/data-export` | `ExportMyDataService` | (téléchargement direct JSON) |
| **Rectification / effacement / limitation** | `POST /account/profile` + `POST /account/delete` | `UpdateProfileService` + `DeleteAccountService` (spec 01) | `account/index.php` |
| **Opposition** | `POST /account/oppose-research` | `OpposeResearchUseService` | `account/index.php` (section "Consentement recherche") |

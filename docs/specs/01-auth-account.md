# Spec 01 — Auth & Account

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations
- **État** : **implémenté** (MVC) — sauf reset de mot de passe (cf. § Pas fait).

## 0bis. État d'implémentation (dev — MVC)

> Mise à jour **2026-06-09**. Le code est en **MVC**
> (`Controllers\` / `Services\` / `Models\` / `Domain\`), pas dans la Clean
> Architecture (`App\…`, `RepositoryInterface`, DTOs) décrite aux §3→6 — ces
> blocs valent comme **intention de design historique** ; ce bloc-ci décrit
> l'**état réel** et fait foi.

### ✅ Fait
- **Connexion / déconnexion** — `AuthController` + `AuthService::login/logout` (session PHP, `session_regenerate_id(true)`). Le login **purge** l'identité super admin résiduelle (exclusivité, cf. [SPEC-superadmin-auth](./SPEC-superadmin-auth.md)).
- **Inscription** — `AuthService::register` : validation, **rôle auto par domaine** (table `email_domain_configs`, pas de hardcode ; domaine inconnu/inactif → refus), unicité email, création atomique user + rôle (`UserRepository::createUserWithRole`). Deux chemins : **membre** (`prepareMemberRegistration` : place + département → `student`/`teacher`) ou **chercheur** si la case `is_researcher` est cochée (`prepareResearcherRegistration` : rôle `researcher`, `laboratory_id` dérivé du domaine email, `department_id` reste NULL).
- **Vérification d'email** (token + lien par mail) — *ajout hors spec d'origine* : `GET /verify-email`, `MailService` (SMTP brut → Mailpit en dev), colonnes `users.email_verified_at` / `email_verify_token`. `register` renvoie `pending_verification` et **ne connecte pas** : le login reste bloqué tant que l'email n'est pas vérifié.
- **Désactivation / réactivation** de compte — `POST /profile/deactivate`, `POST /reactivate`.
- **Édition du profil** (prénom / nom) — `POST /profile/update` → `AuthService::updateProfile` (synchronise la session).
- **Changement de mot de passe** — `POST /profile/password` → `AuthService::changePassword` (vérifie l'actuel, ≥ 8 car., ≠ ancien, re-hash bcrypt).
- **Préférence thème** (auto / clair / sombre) persistée (`users.theme`) — `POST /profile/theme`.
- **Rôle unique** résolu par `AuthService::resolveRoles` depuis `students` / `teachers` / `researchers` / `department_administrators`. ⚠️ Un utilisateur tient **au plus un** rôle : le trigger `enforce_role_exclusivity()` ([`02_triggers.sql`](../../database/schema/02_triggers.sql)) rend les rôles **mutuellement exclusifs** (un enseignant n'est donc **pas** aussi étudiant).

### 🟡 Partiel / divergent
- **Page compte** : sous `/profile` (et non `/account`), MVP — pas d'agrégat stats (`GetAccountOverviewService` non implémenté).
- **Rattachement département** : via selects **lieu + département** dépendants (et non « code département » du §2bis, jamais implémenté). Le `department_id` est écrit et validé serveur ; un AJAX `GET /places/{id}/departments` peuple le second select.
- **Mention RGPD** : checkbox bloquante à l'inscription + page `/gdpr_consent` ; pas de page publique `/privacy` (cf. [spec 06](./06-rgpd.md)).

### ❌ Pas fait
- **Réinitialisation du mot de passe oublié** (`/password/forgot`, `/password/reset`, table `password_reset`, mail de reset) — *must-have non couvert*. (La table `password_reset` n'existe pas au schéma.)
- **Réglage de la durée d'archivage** : la colonne `users.archive_duration_days` existe, mais aucune UI ni service.
- **Suppression de compte automatisée** (soft-delete + anonymisation) — remplacée par désactivation + demande à `dpo@univ-amu.fr`.
- **Préférences densité / langue**.

### Routes réelles (vs §6 planifié)
Présentes : `GET /login`, `POST /login`, `GET /register`, `POST /register`, `GET /logout`, `POST /reactivate`, `GET /gdrp_consent`, `GET /verify-email`, `GET /places/{id}/departments`, `GET /profile`, `POST /profile/update`, `POST /profile/password`, `POST /profile/theme`, `POST /profile/deactivate`. Les routes `/account/*` et `/password/*` du §6 ne sont **pas** en place (le compte vit sous `/profile`).

## 1. Objectifs

Gérer l'identité de l'utilisateur :
- inscription (avec règles sur le domaine email AMU),
- connexion / déconnexion (session PHP),
- réinitialisation de mot de passe (token envoyé par mail),
- consentement RGPD bloquant l'usage tant qu'il n'est pas donné,
- page **Mon compte** : profil, apparence, données, suppression.

## 2. User stories

- En tant qu'**étudiant** (`@etu.univ-amu.fr`), je veux créer un compte
  qui me reconnaît automatiquement comme étudiant.
- En tant qu'**enseignant** (`@univ-amu.fr`), je veux que mon compte
  soit étudiant + enseignant automatiquement.
- En tant qu'**utilisateur**, je veux retrouver l'accès si j'oublie mon
  mot de passe.
- En tant qu'**utilisateur**, je veux pouvoir changer mon mot de passe,
  retirer mon consentement RGPD, supprimer mon compte.
- En tant qu'**utilisateur**, je veux choisir mon thème / ma densité /
  ma langue (stocké localement).
- En tant qu'**utilisateur**, je veux régler la **durée d'archivage**
  de mes conversations, exprimée **en jours** (colonne
  `users.archive_duration_days`, stockée en DB, propre à mon compte).
- En tant qu'**étudiant / enseignant**, je veux **rejoindre mon
  département** en saisissant un **code département** au moment de
  l'inscription (cf. §2bis).

> **Domaines email paramétrables** (rapport §2.3.2) — la détection
> automatique du rôle ne doit **pas** hardcoder `etu.univ-amu.fr` /
> `univ-amu.fr`. Tout passe par `config.domains` :
> ```php
> 'domains' => [
>     'student' => ['etu.univ-amu.fr'],
>     'teacher' => ['univ-amu.fr'],
> ],
> ```
> Pour déployer ailleurs qu'à AMU, on modifie la config, pas le code.

## 2bis. Rattachement au département

> ⚠️ **Non implémenté tel quel.** Le « code département » ci-dessous était la
> piste initiale ; en pratique le formulaire d'inscription utilise **deux
> selects dépendants lieu + département** (`place_id` puis `department_id`,
> peuplé par l'AJAX `GET /places/{id}/departments`). Le principe
> « un utilisateur = un département (sauf chercheur / super admin) » reste vrai.

- **Code département à l'inscription** — l'utilisateur rejoint son
  département en saisissant un **code département** dans le formulaire
  d'inscription. Ce code résout vers un `departments.id`, écrit dans
  `users.department_id`.
- **Un utilisateur = un seul département** (`users.department_id` est un FK
  unique, nullable). **Exceptions** :
  - le **chercheur** n'est rattaché à **aucun** département
    (`department_id` reste `NULL`) — il demande l'accès aux données d'un ou
    plusieurs départements via `researcher_authorizations` ;
  - le **super administrateur** n'est pas un `users` du tout (table
    séparée `super_administrators`, cf. [spec 05 §A.0](./05-admin-research.md)).
- Le `RegisterUserService` valide le code : code inconnu / département
  inactif → erreur de validation, on ne crée pas le compte.

## 3. Domaine

> ⚠️ **Design hexagonal historique — non représentatif du code.** Les §3→6
> décrivent l'intention initiale (entités riches `User`, value objects
> `Email`/`GdprConsent`, `UserRepositoryInterface`, `RegisterUserService`,
> DTOs…). Le code réel est plus direct :
> - **pas d'entité `User`** : `Models\UserRepository` renvoie des tableaux
>   associatifs ; l'identité de session vit dans `$_SESSION` (`currentUser()`).
> - **un seul service** `Services\AuthService` (login, register, profil, mot
>   de passe, (ré)activation) au lieu d'un service par cas d'usage.
> - **pas de value object** `Email`/`UserRole`/`GdprConsent` : validation
>   inline dans `AuthService`, rôles en `string` dans `$_SESSION['roles']`.
> Le bloc reste à titre de référence de conception.

### Entities — `App\Domain\Entities`

```php
final class User
{
    public function __construct(
        private readonly int $id,
        private Email $email,
        private string $passwordHash,
        private string $firstName,
        private string $lastName,
        private bool $isActive,
        private GdprConsent $gdpr,
        private int $conversationArchiveDays,   // préférence perso (cf. §2)
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $lastLogin,
    ) {}

    public function verifyPassword(string $plain): bool;
    public function changePassword(string $newPlainPassword): void;
    public function renameTo(string $firstName, string $lastName): void;
    public function recordLogin(DateTimeImmutable $now): void;
    public function grantGdprConsent(DateTimeImmutable $now): void;
    public function revokeGdprConsent(): void;
    public function setArchiveDays(int $days): void;     // bornes 30..3650
    public function deactivate(): void;
    // …
}
```

> **Lien fort avec la spec 06 (RGPD)** — les méthodes
> `grantGdprConsent`, `revokeGdprConsent` ne couvrent que le
> consentement global. Le **droit d'opposition à la recherche** (toggle
> séparé) et l'**export de mes données** (droit d'accès) sont définis
> dans la [spec 06-rgpd.md](./06-rgpd.md).

### Value Objects — `App\Domain\ValueObjects`

- **`Email`** : valide la forme (`filter_var FILTER_VALIDATE_EMAIL`),
  expose `domain()` pour la détection de rôle.
- **`UserRole`** : enum (`Student`, `Teacher`, `TeacherSpecialised`,
  `Researcher`, `DepartmentAdministrator`). Le **super administrateur**
  n'est PAS un `UserRole` — il vit hors de `users`, dans sa propre table
  (cf. [spec 05 §A.0](./05-admin-research.md)).
- **`GdprConsent`** : `granted: bool`, `at: ?DateTimeImmutable`.

### Interfaces — `App\Domain\Repositories`

```php
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function save(User $user): void;
    /** @return list<UserRole> */
    public function rolesOf(int $userId): array;
    public function attachRole(int $userId, UserRole $role): void;
    public function detachRole(int $userId, UserRole $role): void;
    public function studentNumberOf(int $userId): ?string;
}

interface PasswordResetRepositoryInterface
{
    public function save(PasswordResetToken $token): void;
    public function findByToken(string $token): ?PasswordResetToken;
    public function delete(string $token): void;
    public function deleteAllForUser(int $userId): void;
}
```

## 4. Application (use-cases)

| Service | Méthode | Effet |
|---|---|---|
| `RegisterUserService` *(réel : `AuthService::register`)* | `register(array $data)` | Crée le User. **Résout lieu + département** (cf. §2bis) et écrit `department_id` ; domaine email inconnu / département inactif → erreur. **Attribue le rôle** en lisant `email_domain_configs` (paramétrable, cf. §2) : `student`/`teacher` selon le domaine. ⚠️ **Le rôle `researcher` EST auto-attribué** quand la case `is_researcher` est cochée (lab dérivé du domaine, département NULL) — corrige la note initiale. Seul `teacher_specialised` (`is_specialised`) reste réservé à l'admin de département. |
| `LoginService` | `execute(string $email, string $password)` | Vérifie, met à jour `lastLogin`, retourne le User ou lance `InvalidCredentialsException`. |
| `RequestPasswordResetService` | `execute(Email $email)` | Génère un token, envoie un mail via `MailerInterface`. |
| `ResetPasswordService` | `execute(string $token, string $newPassword)` | Vérifie le token (TTL 1h), change le mdp, invalide tous les tokens du user. |
| `UpdateProfileService` | `execute(int $userId, string $first, string $last)` | Renomme + persiste. |
| `UpdatePreferencesService` | `execute(int $userId, int $archiveDays)` | Met à jour la **durée d'archivage** des conversations du user. Bornes : 30 → 3650 jours. |
| `ChangePasswordService` | `execute(int $userId, string $current, string $new)` | Vérifie l'ancien, hash le nouveau. |
| `GrantGdprConsentService` | `execute(int $userId)` | Met à jour `gdpr_consent_at`. |
| `RevokeGdprConsentService` | `execute(int $userId)` | Inverse + déconnecte. |
| `DeleteAccountService` | `execute(int $userId, string $password)` | Soft-delete (passe `is_active=false`, anonymise email). |
| `GetAccountOverviewService` | `execute(int $userId): AccountOverviewView` | Aggrège profil + stats conversations + bytes pour la page Mon compte. |

### DTOs

```php
final class RegisterRequest {
    public function __construct(
        public readonly Email $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $password,        // déjà validé en longueur
        public readonly string $departmentCode,  // code département (cf. §2bis)
    ) {}
}

final class AccountOverviewView {
    public string $fullName;
    public string $email;
    public ?string $studentNumber;
    /** @var list<string> */ public array $roleLabels;
    public int $conversationCount;
    public int $interactionCount;
    public int $bytesStored;
    public bool $gdprConsent;
    public ?string $gdprConsentAt;
}
```

## 5. Infrastructure

- `PdoUserRepository` implémente `UserRepositoryInterface`.
  - Hydrate depuis les tables `users`, `students`, `teachers`,
    `researchers`, `department_administrators`. (Le super admin a son
    propre repository, distinct — il n'est pas dans `users`.)
- `PdoPasswordResetRepository`.
- `MailerInterface` (port) + `PhpMailerSender` (adapter SMTP) +
  `LogMailerSender` (dev — écrit dans un fichier log).

## 6. HTTP

### Routes

```
GET   /login                  AuthController::showLogin
POST  /login                  AuthController::login
GET   /register               AuthController::showRegister
POST  /register               AuthController::register
GET   /logout                 AuthController::logout
GET   /password/forgot        PasswordResetController::showForgot
POST  /password/forgot        PasswordResetController::sendReset
GET   /password/reset/{token} PasswordResetController::showReset
POST  /password/reset         PasswordResetController::reset

GET   /gdpr/consent           GdprController::show
POST  /gdpr/consent           GdprController::handle

GET   /account                AccountController::index
POST  /account/profile        AccountController::updateProfile
POST  /account/password       AccountController::changePassword
POST  /account/revoke-consent AccountController::revokeConsent
POST  /account/delete         AccountController::delete
```

### Views (`app/Views/pages/`)

- `auth/login.php`, `auth/register.php`, `auth/forgot.php`, `auth/reset.php`
- `auth/gdpr_consent.php`
- `account/index.php` (5 sections : profil, apparence, données,
  consentement recherche, zone risquée)

## 7. Base de données

Tables existantes (source de vérité :
[`01_schema.sql`](../../database/schema/01_schema.sql)) : `users`,
`students`, `teachers`, `researchers`, `department_administrators`,
`super_administrators`.

> La durée d'archivage des conversations existe déjà au schéma sous le nom
> `users.archive_duration_days` (SMALLINT, **en jours**, `> 0`). Ne pas
> recréer une colonne `conversation_archive_days`.

### Nouvelles colonnes / tables

> **État dev (2026-06-09)** — la **vérification d'email** est intégrée
> **directement dans [`01_schema.sql`](../../database/schema/01_schema.sql)**
> (il n'y a **pas** de dossier `database/migrations/`) : `users` porte
> `email_verified_at TIMESTAMPTZ`, `email_verify_token VARCHAR(255)`
> (unique `uq_users_email_verify_token`), `theme`, `archive_duration_days`,
> et **`research_opposed BOOLEAN NOT NULL DEFAULT FALSE`** (déjà au schéma —
> cf. [spec 06](./06-rgpd.md) ; aucun endpoint ne l'écrit encore). La table
> `password_reset` ci-dessous **n'est pas** créée (reset non implémenté).

```sql
-- Proposé (non créé) — à intégrer dans database/schema/01_schema.sql
CREATE TABLE password_reset (
    token       VARCHAR(64) PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

> La table `data_access_log` (journalisation RGPD) reste à introduire — cf.
> [spec 06-rgpd.md](./06-rgpd.md).

## 8. Réutilisation POC

| Fichier POC | Action |
|---|---|
| `app/services/AuthService.php` | Logique login/register à extraire dans `LoginService` + `RegisterUserService`. |
| `app/services/Mailer.php` | Devient `PhpMailerSender` derrière `MailerInterface`. |
| `app/controllers/LoginController.php` | Inspirer le flow ; le nouveau controller délègue tout à des services. |
| `app/controllers/PasswordResetController.php` | Idem. |
| `app/controllers/AccountController.php` | Idem — particulièrement l'agrégation pour `AccountOverviewView`. |
| `app/views/pages/account/index.php` | À reprendre en l'état pour le HTML, juste rebrancher les variables. |
| `app/views/pages/auth/*` | Idem. |

## 9. Tests

| Niveau | Cible | Exemple |
|---|---|---|
| Unit Domain | `Email` | Lance sur "foo", OK sur "a@b.fr". |
| Unit Domain | `User::verifyPassword` | Reconnaît un hash bcrypt valide. |
| Unit Application | `LoginService` avec `InMemoryUserRepository` | Renvoie le user / lance `InvalidCredentialsException`. |
| Unit Application | `RegisterUserService` avec `LogMailerSender` | Crée le user, n'envoie pas de mail (pas de mail dans register). |
| Unit Application | `ResetPasswordService` avec un repo mocké | Token expiré → exception. |
| Integration | `PdoUserRepository::save` puis `findById` | Round-trip exact. |
| Acceptance | `POST /login` avec creds valides | Cookie de session posé, redirect /chat. |

## 10. Anti-patterns spécifiques

- ❌ Validation du format email dans le service (c'est le rôle de `Email`).
- ❌ Hash bcrypt directement dans le controller — passe par `User::changePassword`.
- ❌ Envoi du mail synchrone bloquant dans le controller — c'est le service
  qui appelle le Mailer (et en prod, on pourra basculer en queue async).
- ❌ Stocker le token de reset en clair côté DB **sans expiration**.

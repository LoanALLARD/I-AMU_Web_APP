# Spec 01 — Auth & Account

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations
- **État POC** : implémenté

## 0bis. État d'implémentation (dev — MVC)

> Mise à jour **2026-06-04**. Le code de `dev` est en **MVC**
> (`Controllers\` / `Services\` / `Models\` / `Domain\`), pas dans la Clean
> Architecture (`App\…`) décrite aux §3→6 — celles-ci valent comme
> **intention de design** ; ce bloc décrit l'**état réel**.

### ✅ Fait
- **Connexion / déconnexion** — `AuthController` + `AuthService::login/logout` (session PHP, `session_regenerate_id`).
- **Inscription** — `AuthService::register` : validation, **rôle auto par domaine paramétrable** (table `email_domain_configs`, pas de hardcode), unicité email, création atomique user + rôle (`UserRepository::createUserWithRole`).
- **Vérification d'email** (token + lien par mail) — *ajout hors spec d'origine* : `GET /verify-email`, `MailService`, colonnes `users.email_verified_at` / `email_verify_token`. Le login est bloqué tant que l'email n'est pas vérifié.
- **Désactivation / réactivation** de compte — `POST /profile/deactivate`, `POST /reactivate`.
- **Édition du profil** (prénom / nom) — `POST /profile/update` → `AuthService::updateProfile` (synchronise la session).
- **Changement de mot de passe** — `POST /profile/password` → `AuthService::changePassword` (vérifie l'actuel, ≥ 8 car., ≠ ancien, re-hash bcrypt).
- **Préférence thème** (auto / clair / sombre) persistée (`users.theme`) — `POST /profile/theme`.
- **Rôles multiples** résolus depuis `students` / `teachers` / `department_administrators`.

### 🟡 Partiel / divergent
- **Page compte** : sous `/profile` (et non `/account`), MVP — pas d'agrégat stats (`GetAccountOverviewService` non implémenté).
- **Rattachement département** : via selects **lieu + département** dépendants (et non « code département » du §2bis). Le `department_id` est bien écrit et validé serveur.
- **Mention RGPD** : checkbox bloquante à l'inscription + page `/rgpd-consent` ; pas de page publique `/privacy` (cf. [spec 06](./06-rgpd.md)).

### ❌ Pas fait
- **Réinitialisation du mot de passe oublié** (`/password/forgot`, `/password/reset`, table `password_reset`, mail de reset) — *must-have non couvert*.
- **Réglage de la durée d'archivage** : la colonne `users.archive_duration_days` existe, mais aucune UI ni service.
- **Suppression de compte automatisée** (soft-delete + anonymisation) — remplacée par désactivation + demande à `dpo@univ-amu.fr`.
- **Préférences densité / langue**.

### Routes réelles (vs §6 planifié)
Présentes : `GET /profile`, `POST /profile/update`, `POST /profile/password`, `POST /profile/theme`, `POST /profile/deactivate`, `POST /reactivate`, `GET /rgpd-consent`, `GET /verify-email`. Les routes `/account/*` et `/password/*` du §6 ne sont **pas** en place.

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
| `RegisterUserService` | `execute(RegisterRequest)` | Crée le User. **Résout le code département** (cf. §2bis) et écrit `department_id` ; code inconnu / département inactif → erreur. **Attribue les rôles auto** en lisant `config.domains` (paramétrable, cf. §2) : si l'email matche un domaine de la liste `student`/`teacher`, le rôle est ajouté. Les rôles `researcher` et `teacher_specialised` ne sont jamais auto-attribués (admin uniquement). |
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

> **État dev (2026-06-04)** — la **vérification d'email** (ajout) a introduit
> dans `users` : `email_verified_at TIMESTAMPTZ` + `email_verify_token
> VARCHAR(255)` (contrainte unique `uq_users_email_verify_token`). En revanche
> la table `password_reset` ci-dessous **n'est pas** créée (reset de mot de
> passe non implémenté).

```sql
-- database/migrations/AAAA-MM-DD-password-reset.sql
CREATE TABLE password_reset (
    token       VARCHAR(64) PRIMARY KEY,
    user_id     BIGINT NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

> Les autres colonnes RGPD (`research_opposed`, table
> `data_access_log`) sont introduites par la migration de la
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

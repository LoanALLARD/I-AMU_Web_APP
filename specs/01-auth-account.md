# Spec 01 — Auth & Account

## 0. Statut
- **Priorité** : must-have
- **Dépend de** : 00-foundations
- **État POC** : implémenté

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
  de mes conversations (stocké en DB, propre à mon compte).

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
  `Researcher`, `Admin`).
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
| `RegisterUserService` | `execute(RegisterRequest)` | Crée le User. **Attribue les rôles auto** en lisant `config.domains` (paramétrable, cf. §2) : si l'email matche un domaine de la liste `student`/`teacher`, le rôle est ajouté. Les rôles `researcher` et `teacher_specialised` ne sont jamais auto-attribués (admin uniquement). |
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
  - Hydrate depuis les tables `"user"`, `student`, `teacher`,
    `researcher`, `administrator`.
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

Tables existantes : `"user"`, `student`, `teacher`, `researcher`,
`administrator`.

### Nouvelles colonnes / tables

```sql
-- database/migrations/AAAA-MM-DD-user-preferences.sql
ALTER TABLE "user"
    ADD COLUMN IF NOT EXISTS conversation_archive_days INT NOT NULL DEFAULT 180;

CREATE TABLE password_reset (
    token       VARCHAR(64) PRIMARY KEY,
    user_id     INT NOT NULL REFERENCES "user"(user_id) ON DELETE CASCADE,
    expires_at  TIMESTAMP NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

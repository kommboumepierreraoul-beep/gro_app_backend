# Auth System — Guide de configuration complet

## 1. Installation des dépendances

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## 2. Placement des fichiers

| Fichier généré                        | Destination dans le projet                          |
|---------------------------------------|-----------------------------------------------------|
| `AuthController.php`                  | `app/Http/Controllers/Api/`                         |
| `EmailVerificationNotification.php`   | `app/Notifications/`                                |
| `ResetPasswordNotification.php`       | `app/Notifications/`                                |
| `User.php`                            | `app/Models/`                                       |
| `EnsureEmailIsVerified.php`           | `app/Http/Middleware/`                              |
| `create_users_table.php`              | `database/migrations/` (renommer avec timestamp)    |
| `api.php`                             | `routes/`                                           |

---

## 3. Enregistrer le middleware

### Laravel 11 — `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'verified.email' => \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
})
```

### Laravel 10 — `app/Http/Kernel.php`

```php
protected $middlewareAliases = [
    // ...
    'verified.email' => \App\Http\Middleware\EnsureEmailIsVerified::class,
];
```

---

## 4. Configuration `.env`

```env
# Mail (exemple avec Mailtrap pour les tests)
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_user
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourapp.com"
MAIL_FROM_NAME="${APP_NAME}"

# Cache (Redis recommandé en production)
CACHE_DRIVER=redis   # ou 'database' ou 'file' pour les tests

# Queue (pour les notifications asynchrones)
QUEUE_CONNECTION=redis  # ou 'sync' pour tester sans worker
```

---

## 5. Cache driver "database" (optionnel)

Si vous utilisez `CACHE_DRIVER=database` :

```bash
php artisan cache:table
php artisan migrate
```

---

## 6. Migrations

```bash
php artisan migrate
```

---

## 7. Lancer le worker de queue (production)

```bash
php artisan queue:work --queue=default
```

Pour les tests locaux, vous pouvez utiliser `QUEUE_CONNECTION=sync` dans `.env`
afin que les emails soient envoyés de manière synchrone sans worker.

---

## 8. Endpoints API — Référence

### Publics (pas de token)

| Méthode | URL                        | Description                        |
|---------|----------------------------|------------------------------------|
| POST    | `/api/auth/register`       | Inscription utilisateur            |
| POST    | `/api/auth/register/admin` | Inscription admin                  |
| POST    | `/api/auth/login`          | Connexion → retourne un token      |
| POST    | `/api/auth/forgot-password`| Envoyer le code de réinitialisation|
| POST    | `/api/auth/reset-password` | Réinitialiser avec le code OTP     |

### Authentifiés (Bearer token)

| Méthode | URL                        | Description                        |
|---------|----------------------------|------------------------------------|
| POST    | `/api/auth/email/resend`   | Renvoyer le code de vérification   |
| POST    | `/api/auth/email/verify`   | Vérifier l'email avec le code OTP  |
| POST    | `/api/auth/refresh`        | Rafraîchir le token                |
| POST    | `/api/auth/logout`         | Déconnecter (token actuel)         |
| POST    | `/api/auth/logout/all`     | Déconnecter tous les appareils     |

### Authentifiés + Email vérifié

| Méthode | URL                  | Description               |
|---------|----------------------|---------------------------|
| GET     | `/api/auth/profile`  | Voir le profil            |
| PUT     | `/api/auth/profile`  | Modifier le profil        |
| PUT     | `/api/auth/password` | Changer le mot de passe   |

---

## 9. Flux complet de vérification email

```
1. POST /api/auth/register
   ← { token, user }
   → Email envoyé avec un code OTP à 6 chiffres (valide 10 min)

2. POST /api/auth/email/verify    [Bearer token]
   Body: { "code": "123456" }
   ← { success: true, user }

3. Accès aux routes protégées par 'verified.email'
```

---

## 10. Flux de réinitialisation du mot de passe

```
1. POST /api/auth/forgot-password
   Body: { "email": "user@example.com" }
   → Email envoyé avec un code OTP (valide 15 min, throttle 2 min)

2. POST /api/auth/reset-password
   Body: { "email": "...", "code": "123456", "password": "...", "password_confirmation": "..." }
   ← { success: true }
   → Tous les tokens existants sont révoqués
```

---

## 11. Activer la restriction login (email non vérifié)

Dans `AuthController::login()`, décommentez le bloc :

```php
if (!$user->hasVerifiedEmail()) {
    Auth::logout();
    return response()->json([
        'success' => false,
        'message' => 'Please verify your email before logging in.',
    ], 403);
}
```

---

## 12. Sécurité — Points importants

- Les codes OTP sont stockés **uniquement en cache** (pas en base de données).
- Le code de reset password est throttlé à **1 envoi toutes les 2 minutes**.
- En production, **`app.debug = false`** pour masquer les messages d'exception.
- `logoutAll()` révoque **tous les tokens Sanctum** de l'utilisateur.
- Le changement de mot de passe révoque tous les tokens et en génère un nouveau.

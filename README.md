# Tike API

Laravel 12 REST API with OAuth2 authentication (Passport) and queue-based email verification.

---

## Additional Documentation

- [Commands And Jobs (Football Data)](docs/CommandsAndJobs.md)
- [League Structure](docs/LeagueStructure.md)

---

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Docker (recommended)

---

## Installation

### 1. Environment Configuration

Copy the environment template:

```bash
cp .env.example .env
```

Configure your database and queue settings in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tike
DB_USERNAME=tike
DB_PASSWORD=tike

QUEUE_CONNECTION=redis
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Run Database Migrations

```bash
php artisan migrate
```

### 5. Generate OAuth Keys

```bash
php artisan passport:keys
```

### 6. Seed Database (Optional)

```bash
php artisan db:seed
```

---

## Queue Workers

The application uses queues for background processing (e.g., email verification).

### Production Mode

Run the queue worker as a persistent process:

```bash
php artisan queue:work redis --queue=emails --tries=3 --timeout=60
```

Run the football-data queue worker as a persistent process:

```bash
php artisan queue:work redis --queue=football-data --tries=3 --timeout=60
```

**Important:** In production, use a process manager like Supervisor to automatically restart the worker.

### Development Mode

Use `queue:listen` for automatic code reload:

```bash
php artisan queue:listen --queue=emails --verbose --tries=3 --timeout=60
```

Use `queue:listen` for football-data automatic code reload:

```bash
php artisan queue:listen --queue=football-data --verbose --tries=3 --timeout=60
```

---

## Logging

### Default Log Location

Logs are stored in: `storage/logs/laravel.log`

### View Logs in Real-Time

```bash
tail -f storage/logs/laravel.log
```

### Custom Log Configuration

Edit `config/logging.php` to customize log channels and paths.

**Change log path via environment:**

```env
LOG_CHANNEL=daily
LOG_LEVEL=info
```

**Custom path in `config/logging.php`:**

```php
'daily' => [
    'driver' => 'daily',
    'path' => env('LOG_PATH', storage_path('logs/laravel.log')),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
],
```

Then set in `.env`:

```env
LOG_PATH=/var/log/tike-api/app.log
```

---

## API Documentation

### Authentication Endpoints

#### Register New User
```http
POST /api/v1/auth/sign-up
Content-Type: application/json

{
  "name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "password": "SecurePass123",
  "password_confirmation": "SecurePass123",
  "language": "en"  // Optional: 'es' or 'en' (default: 'es')
}
```

**Response:** `201 Created`

```json
{
  "message": "User registered successfully.",
  "user": {
    "id": 1,
    "name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "role": "user",
    "language": "en"
  }
}
```

**Notes:**
- `language` is optional and must be exactly 2 characters
- Supported languages: `es` (Spanish), `en` (English)
- If not provided or invalid, defaults to `es`

#### Get Access Token
```http
POST /api/v1/auth/token
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePass123"
}
```

**Response:** `200 OK` with JWT token

**Note:** Email must be verified to obtain a token.

#### Verify Email
```http
POST /api/v1/auth/verify-email
Authorization: Bearer {verification_token}
```

**Response:** `200 OK`

**Note:** The verification token must have the `user:verify` scope. After verification, the token is revoked.

#### Update Password
```http
PATCH /api/v1/auth/password
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "current_password": "OldPassword123",
  "new_password": "NewSecurePass456",
  "new_password_confirmation": "NewSecurePass456"
}
```

**Response:** `200 OK`

```json
{
  "message": "Password updated successfully."
}
```

**Requirements:**
- User must be authenticated
- Requires `user:update-password` scope
- `current_password` must match the user's current password
- `new_password` must meet minimum strength requirements:
  - At least 8 characters
  - Mixed case letters (uppercase and lowercase)
  - Contains numbers
- `new_password_confirmation` must match `new_password`

---

## API Key Authentication

### Overview

All API endpoints (except `/api/v1/health`) require a valid API key sent via the `X-API-Key` header. This security layer prevents unauthorized access to the API while maintaining ease of development.

### Route Protection Levels

The API implements three levels of security:

1. **No protection**: `/api/v1/health` (for monitoring services)
2. **API Key only**: Public endpoints that don't require user authentication
   - `/api/v1/countries` - Get all countries
   - `/api/v1/countries/{code}` - Get country by code
   - `/api/v1/auth/sign-up` - Register new user
   - `/api/v1/auth/get-token` - Login / Get OAuth token
   - `/api/v1/auth/password/forgot` - Request password reset
3. **API Key + OAuth**: Protected endpoints requiring both API key and user authentication
   - `/api/v1/auth/verify-email` - Verify email address
   - `/api/v1/auth/password` - Update password
   - `/api/v1/auth/password/reset` - Reset password with token
   - All other authenticated endpoints

### Development API Keys

The database seeder automatically creates 4 fixed API keys for development. These keys never change, making development workflow seamless:

| Platform | API Key | Rate Limit |
|----------|---------|------------|
| **iOS** | `dev_ios_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa` | 1000/min |
| **Android** | `dev_android_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb` | 1000/min |
| **Web** | `dev_web_cccccccccccccccccccccccccccccccccccccccccccccccccccccccc` | 1000/min |
| **Testing** | `dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd` | 10000/min |

These keys are created by the `ApiKeySeeder` when running:

```bash
php artisan migrate:fresh --seed
# or
php artisan db:seed --class=ApiKeySeeder
```

### Usage Examples

#### cURL

**Public endpoint (API key only):**
```bash
curl -H "X-API-Key: dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd" \
     http://localhost:8080/api/v1/countries
```

**Sign up a new user:**
```bash
curl -X POST \
     -H "X-API-Key: dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "John",
       "last_name": "Doe",
       "email": "john@example.com",
       "country_code": "+1",
       "phone_number": "1234567890",
       "password": "Test123",
       "password_confirmation": "Test123"
     }' \
     http://localhost:8080/api/v1/auth/sign-up
```

**Protected endpoint (API key + OAuth token):**
```bash
curl -X POST \
     -H "X-API-Key: dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd" \
     -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"token": "verification_token"}' \
     http://localhost:8080/api/v1/auth/verify-email
```

#### JavaScript (fetch)

**Public endpoint:**
```javascript
fetch('http://localhost:8080/api/v1/countries', {
  headers: {
    'X-API-Key': 'dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd'
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

**Protected endpoint:**
```javascript
fetch('http://localhost:8080/api/v1/auth/verify-email', {
  method: 'POST',
  headers: {
    'X-API-Key': 'dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
    'Authorization': `Bearer ${accessToken}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ token: verificationToken })
})
.then(response => response.json())
.then(data => console.log(data));
```

#### Postman

1. **Add API Key header to all requests:**
   - Key: `X-API-Key`
   - Value: `dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd`

2. **For authenticated endpoints, also add Authorization header:**
   - Key: `Authorization`
   - Value: `Bearer YOUR_ACCESS_TOKEN`

3. **Tip:** Save the API key in Postman environment variables for easy reuse:
   - Variable: `api_key`
   - Value: `dev_test_ddddddddddddddddddddddddddddddddddddddddddddddddddddddd`
   - Use: `{{api_key}}` in headers

### Error Responses

#### Missing API Key
**Status:** `401 Unauthorized`
```json
{
  "message": "API key is required.",
  "error": "Missing X-API-Key header"
}
```

#### Invalid API Key
**Status:** `401 Unauthorized`
```json
{
  "message": "Invalid API key.",
  "error": "The provided API key is not valid"
}
```

#### Inactive or Expired Key
**Status:** `401 Unauthorized`
```json
{
  "message": "API key is inactive or expired."
}
```

### Managing API Keys

#### View all API keys:
```bash
php artisan tinker
>>> App\Models\ApiKey::all();
```

#### Create a new API key:
```bash
php artisan tinker
>>> App\Models\ApiKey::generate('My App Name', 'ios', 1000);
# Returns: ApiKey object with generated key
```

#### Deactivate an API key:
```bash
php artisan tinker
>>> $key = App\Models\ApiKey::where('platform', 'ios')->first();
>>> $key->update(['is_active' => false]);
```

#### Reactivate an API key:
```bash
php artisan tinker
>>> $key = App\Models\ApiKey::where('platform', 'ios')->first();
>>> $key->update(['is_active' => true]);
```

#### Check API key last usage:
```bash
php artisan tinker
>>> App\Models\ApiKey::where('platform', 'testing')->first()->last_used_at;
```

#### Set expiration date:
```bash
php artisan tinker
>>> $key = App\Models\ApiKey::find(1);
>>> $key->update(['expires_at' => now()->addMonths(6)]);
```

### Database Schema

The `api_keys` table structure:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | varchar(255) | Descriptive name (e.g., "iOS Production App") |
| `key` | varchar(64) | Unique 64-character API key |
| `platform` | varchar(50) | Platform identifier (ios, android, web, etc.) |
| `is_active` | boolean | Enable/disable flag (default: true) |
| `rate_limit` | integer | Requests per minute (default: 100) |
| `last_used_at` | timestamp | Last time the key was used (nullable) |
| `expires_at` | timestamp | Optional expiration date (nullable) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

**Indexes:**
- Unique index on `key`
- Index on `platform`
- Index on `is_active`

### Production Considerations

⚠️ **Security Warning:** The development API keys are for local development only. **Never use them in production.**

#### For Production Deployment:

1. **Generate Secure Keys:**
   ```bash
   php artisan tinker
   >>> App\Models\ApiKey::generate('iOS Production', 'ios', 10000);
   >>> App\Models\ApiKey::generate('Android Production', 'android', 10000);
   >>> App\Models\ApiKey::generate('Web Production', 'web', 5000);
   ```

2. **Store Keys Securely:**
   - Use environment variables or secure vault services
   - Never commit production keys to version control
   - Distribute keys through secure channels only

3. **Rotate Keys Periodically:**
   - Generate new keys every 3-6 months
   - Deactivate old keys after transition period
   - Monitor `last_used_at` to ensure old keys are no longer in use

4. **Monitor Usage:**
   - Check `last_used_at` for suspicious activity
   - Review rate limits based on actual traffic
   - Set up alerts for inactive keys being used

5. **Set Appropriate Rate Limits:**
   - Adjust `rate_limit` based on expected traffic per platform
   - Lower limits for testing/staging environments
   - Higher limits for production mobile apps

6. **Configure Expiration:**
   - Set `expires_at` for temporary API keys
   - Useful for beta testers or limited-time access
   - Automatically prevents access after expiration

7. **Platform-Based Tracking:**
   - Use different keys per platform (iOS, Android, Web)
   - Enables platform-specific analytics
   - Allows selective key revocation without affecting other platforms

#### Rate Limiting (Future Enhancement)

The `rate_limit` field is prepared for future implementation. To add active rate limiting:

1. Modify `ValidateApiKey` middleware
2. Track requests per key per minute
3. Return `429 Too Many Requests` when exceeded
4. Consider using Redis for distributed rate limiting

### Testing with API Keys

The test suite uses the `WithApiKey` trait to automatically include API keys in test requests:

```php
use Tests\Traits\WithApiKey;

class MyTest extends TestCase
{
    use RefreshDatabase, WithApiKey;
    
    public function test_endpoint_requires_api_key(): void
    {
        // Automatically includes X-API-Key header
        $response = $this->getJsonWithApiKey('/api/v1/countries');
        $response->assertStatus(200);
    }
    
    public function test_protected_endpoint(): void
    {
        $user = User::factory()->create();
        Passport::actingAs($user, ['user:verify']);
        
        // Includes both API key and Authorization headers
        $response = $this->postJsonWithApiKey('/api/v1/auth/verify-email', [
            'token' => 'some-token'
        ]);
        
        $response->assertStatus(200);
    }
}
```

Available trait methods:
- `getJsonWithApiKey($uri, $headers = [])`
- `postJsonWithApiKey($uri, $data = [], $headers = [])`
- `patchJsonWithApiKey($uri, $data = [], $headers = [])`
- `deleteJsonWithApiKey($uri, $data = [], $headers = [])`

The trait automatically creates the test API key if it doesn't exist in the database.

---

## Email Verification System

### Overview

The application implements a queue-based email verification system using Laravel Notifications.

### Flow

1. **User Registration** → Triggers `UserStored` event
2. **Event Listener** → `SendEmailVerification` (queued on `emails` queue)
3. **Notification** → `EmailVerificationNotification` sends email with verification link
4. **Verification Link** → Contains a Passport token with `user:verify` scope
5. **User Clicks Link** → Calls `/api/v1/auth/verify-email` endpoint
6. **Email Verified** → `email_verified_at` field is set, token is revoked

### Configuration

#### Email Verification URL

Set in `.env`:

```env
EMAIL_VERIFICATION_URL="https://your-frontend-domain.com/verify-email/"
```

The system will append the verification token to this URL.

#### Mail Service Configuration

The system uses Laravel's mail abstraction, so you can easily switch between services:

**SMTP:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

**SendGrid (via SMTP):**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

**Mailgun:**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-mailgun-key
```

**AWS SES:**
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
```

### Localization

Email content is automatically sent in the user's preferred language based on their `language` setting.

Currently supported languages:

- **Spanish (default):** `lang/es/notifications.php`
- **English:** `lang/en/notifications.php`

#### How It Works

1. During registration, users can optionally specify their `language` preference (`es` or `en`)
2. This preference is saved in the `settings` table
3. When sending notifications, the system automatically sets the locale based on the user's language setting
4. If no language is set, it defaults to Spanish (`es`)

#### Adding New Languages

To add support for additional languages:

1. Create a new language file:

```bash
cp lang/es/notifications.php lang/fr/notifications.php
```

2. Translate the content in the new file

3. Add the language to `config/settings.php`:

```php
'language' => [
    'default' => 'es',
    'options' => ['es', 'en', 'fr'],  // Add new language here
    // ...
],
```

4. Users will now be able to select the new language during registration

### Testing Emails Locally

Use `MAIL_MAILER=log` in development to log emails instead of sending them:

```env
MAIL_MAILER=log
```

Emails will be logged to `storage/logs/laravel.log`.

---

## Testing

Run all tests:

```bash
php artisan test
```

Run specific test suite:

```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

Run tests with coverage:

```bash
php artisan test --coverage
```

---

## Common Commands

### Cache Management

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Database Operations

```bash
# Run migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh database with seeders
php artisan migrate:fresh --seed
```

### Queue Management

```bash
# Clear all failed jobs
php artisan queue:flush

# Retry failed jobs
php artisan queue:retry all

# List failed jobs
php artisan queue:failed
```

---

## Production Deployment

### Optimization

Run these commands after deployment:

```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Queue Worker (Supervisor)

Create a Supervisor configuration file `/etc/supervisor/conf.d/tike-api-worker.conf`:

```ini
[program:tike-api-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/tike-api/artisan queue:work --queue=emails --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/tike-api/storage/logs/worker.log
stopwaitsecs=3600
```

Reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tike-api-worker:*
```

---

## Troubleshooting

### Clear All Caches

```bash
php artisan optimize:clear
```

### Permission Issues

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Queue Not Processing

Check if the worker is running:

```bash
ps aux | grep queue:work
```

Restart the queue worker:

```bash
php artisan queue:restart
```

---

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Passport](https://laravel.com/docs/passport)
- [Laravel Queues](https://laravel.com/docs/queues)

---

## License

This project is proprietary software. All rights reserved.

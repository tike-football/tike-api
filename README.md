# Tike API

Laravel 12 REST API with OAuth2 authentication (Passport) and queue-based email verification.

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

QUEUE_CONNECTION=database
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
php artisan queue:work --queue=emails --tries=3 --timeout=60
```

**Important:** In production, use a process manager like Supervisor to automatically restart the worker.

### Development Mode

Use `queue:listen` for automatic code reload:

```bash
php artisan queue:listen --queue=emails --verbose --tries=3 --timeout=60
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
  "password_confirmation": "SecurePass123"
}
```

**Response:** `201 Created`

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

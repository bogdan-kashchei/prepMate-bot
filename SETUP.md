# SETUP.md — Deployment Guide

Two deployment paths are described: **local development** (PHP + cloudflared) and **Docker** (production-ready).

---

## Requirements

### Local development

| Tool | Version |
|---|---|
| PHP | 8.3+ |
| Composer | 2.x |
| SQLite | bundled with PHP |
| cloudflared | any (for dev tunnel) |

### Docker deployment

| Tool | Version |
|---|---|
| Docker | 24+ |
| Docker Compose | v2 (bundled with Docker Desktop) |

---

## Option A — Local development (php artisan serve)

### Step 1 — Install dependencies

```bash
cd interview-bot-laravel
composer install
```

### Step 2 — Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and fill in the three required secrets:

```dotenv
TELEGRAM_TOKEN=123456789:AABBcc...        # token from @BotFather
WEBHOOK_SECRET=your_random_secret_string  # openssl rand -hex 32
CLAUDE_API_KEY=sk-ant-...                 # console.anthropic.com
```

> **WEBHOOK_SECRET** is sent by Telegram in the `X-Telegram-Bot-Api-Secret-Token` header
> on every webhook request. Generate it once and never change it in production.

### Step 3 — Migrate database

```bash
php artisan migrate
```

Creates `telegram_users`, `questions`, `user_sessions`, `user_answers`.
The SQLite file is created automatically at `database/database.sqlite`.

### Step 4 — Seed questions

```bash
php artisan bot:seed-questions
```

Loads all questions from `questions/*.json`. Safe to re-run — duplicates are skipped.

### Step 5 — Create admin user

```bash
php artisan make:filament-user
```

Enter a name, email, and password for the Filament admin panel at `/admin`.

### Step 6 — Start server

```bash
php artisan serve
```

Server starts at `http://localhost:8000`.

### Step 7 — Create a public tunnel

Telegram requires an HTTPS URL. Use `cloudflared` (no account required):

```bash
cloudflared tunnel --url http://localhost:8000
```

You will get a URL like `https://abc-def-123.trycloudflare.com`. Copy it.

> Alternative: `ngrok http 8000`

### Step 8 — Register webhook

```bash
php artisan bot:set-webhook https://abc-def-123.trycloudflare.com/webhook
```

Expected output:
```
Webhook set successfully: https://abc-def-123.trycloudflare.com/webhook
Response: Webhook was set
```

### Step 9 — Verify

1. Send `/start` to your bot in Telegram
2. Open the admin panel at `http://localhost:8000/admin`

---

## Option B — Docker (production)

The Docker setup uses a two-stage build:
- **Stage 1:** Composer installs production dependencies
- **Stage 2:** `php:8.3-fpm-alpine` with Nginx + PHP-FPM + Supervisor in a single container

Data is persisted via named Docker volumes (`sqlite_data`, `app_logs`).

### Step 1 — Configure environment

```bash
cp .env.example .env
```

Generate an app key (run once — never regenerate after first deploy):

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
# or, if PHP is not installed locally:
docker run --rm php:8.3-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Set the required variables in `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...              # generated above
APP_URL=https://your-domain.com
APP_PORT=8000                   # host port mapped to container port 80

TELEGRAM_TOKEN=...
WEBHOOK_SECRET=...
CLAUDE_API_KEY=...
```

### Step 2 — Build the image

```bash
docker compose build
```

### Step 3 — Start the container

```bash
docker compose up -d
```

The container exposes port `80` internally, mapped to `APP_PORT` on the host (default `8000`).

### Step 4 — First-run initialization

Run these one-time commands inside the running container:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan bot:seed-questions
docker compose exec app php artisan make:filament-user
```

### Step 5 — Register webhook

```bash
docker compose exec app php artisan bot:set-webhook https://your-domain.com/webhook
```

### Step 6 — Verify

```bash
# Container health
docker compose ps

# Application logs
docker compose logs -f app

# Webhook status
curl "https://api.telegram.org/bot<TELEGRAM_TOKEN>/getWebhookInfo"
```

### Common Docker operations

```bash
# Rebuild after code changes
docker compose build && docker compose up -d

# Open a shell inside the container
docker compose exec app sh

# Run an artisan command
docker compose exec app php artisan <command>

# View logs
docker compose logs -f app

# Stop
docker compose down

# Stop and remove volumes (DESTROYS DATABASE)
docker compose down -v
```

---

## Artisan commands reference

| Command | Description |
|---|---|
| `php artisan bot:seed-questions` | Load questions from `questions/*.json` |
| `php artisan bot:seed-questions --fresh` | Wipe questions table first, then reload |
| `php artisan bot:set-webhook [url]` | Register Telegram webhook |
| `php artisan make:filament-user` | Create admin panel user |
| `php artisan migrate` | Run pending migrations |
| `php artisan migrate:fresh --seed` | Drop all tables, migrate, and seed |
| `php artisan optimize:clear` | Clear all cached config/routes/views |

---

## Environment variables reference

```dotenv
# Application
APP_NAME="Interview Bot"
APP_ENV=local|production
APP_KEY=                   # base64-encoded 32-byte key — required
APP_DEBUG=true|false
APP_URL=http://localhost:8000
APP_PORT=8000              # host port for docker-compose

# Database (SQLite — no server required)
DB_CONNECTION=sqlite
# DB_DATABASE=/absolute/path/to/database.sqlite

# Bot — all three are required
TELEGRAM_TOKEN=
WEBHOOK_SECRET=
CLAUDE_API_KEY=

# Filament admin
FILAMENT_ADMIN_EMAIL=admin@admin.com
FILAMENT_ADMIN_PASSWORD=password
```

---

## FAQ

**Q: The tunnel restarted and the URL changed.**

```bash
php artisan bot:set-webhook https://new-url.trycloudflare.com/webhook
# or inside Docker:
docker compose exec app php artisan bot:set-webhook https://new-url.trycloudflare.com/webhook
```

**Q: How do I re-seed after editing question JSON files?**

```bash
php artisan bot:seed-questions       # adds new questions, keeps existing
php artisan bot:seed-questions --fresh  # full reset (WARNING: deletes user answers too)
```

**Q: How do I verify the webhook is active?**

```bash
curl "https://api.telegram.org/bot<TELEGRAM_TOKEN>/getWebhookInfo"
```

**Q: Where are the logs?**

| Deployment | Location |
|---|---|
| Local | `storage/logs/laravel.log` |
| Docker | `docker compose logs -f app` |

**Q: Can I switch to MySQL/PostgreSQL?**

Yes. Update `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`,
add the relevant PHP extension to the Dockerfile (`pdo_mysql` or `pdo_pgsql`), and re-run migrations.

**Q: I see "Webhook was not modified" when registering.**

The URL and secret are the same as already registered — this is not an error. The webhook is working.

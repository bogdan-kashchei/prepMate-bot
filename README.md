# Interview Bot — Laravel Edition

Telegram Interview Practice Bot built on Laravel + Nutgram + Filament.

## Stack

- PHP 8.2+, Laravel 11
- [Nutgram](https://nutgram.dev/) — Telegram bot framework
- [Filament](https://filamentphp.com/) — Admin panel (`/admin`)
- SQLite — local database (no MySQL needed)
- Anthropic Claude API — AI feedback on answers

## Setup

### 1. Install dependencies
```bash
composer install
```

### 2. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and fill in:
```
TELEGRAM_TOKEN=your_bot_token
WEBHOOK_SECRET=some_random_secret
CLAUDE_API_KEY=your_claude_api_key
```

### 3. Run migrations
```bash
php artisan migrate
```

### 4. Seed questions
```bash
php artisan bot:seed-questions
```

### 5. Create admin user
```bash
php artisan make:filament-user
```

### 6. Start the server
```bash
php artisan serve
```

### 7. Expose to the internet (dev)
```bash
cloudflared tunnel --url http://localhost:8000
```

### 8. Register webhook
```bash
php artisan bot:set-webhook https://your-tunnel-url.trycloudflare.com/webhook
```

### 9. Open admin panel
Navigate to: `http://localhost:8000/admin`

## Commands

| Command | Description |
|---|---|
| `php artisan bot:seed-questions` | Load questions from `questions/*.json` into DB |
| `php artisan bot:seed-questions --fresh` | Delete all questions first, then seed |
| `php artisan bot:set-webhook [url]` | Register the Telegram webhook |
| `php artisan make:filament-user` | Create an admin panel user |

## Admin Panel

- `/admin/questions` — Browse, create, edit, delete questions (filter by category/level)
- `/admin/telegram-users` — View bot users and their answers (read-only)
- `/admin` — Dashboard with stats and top questions

## Project Structure

```
app/
  Conversations/
    InterviewConversation.php   # FSM bot conversation
  Services/
    ClaudeService.php           # Anthropic API integration
    QuestionService.php         # Question fetch + save answers
    UserService.php             # Telegram user management
  Models/
    TelegramUser.php
    Question.php
    UserSession.php
    UserAnswer.php
  Filament/
    Resources/
      QuestionResource/         # CRUD for questions
      TelegramUserResource/     # Read-only view of bot users
    Widgets/
      StatsOverviewWidget.php   # Dashboard stats
      TopQuestionsWidget.php    # Top 5 popular questions
  Http/
    Controllers/
      WebhookController.php     # POST /webhook
    Middleware/
      ValidateWebhookSecret.php # X-Telegram-Bot-Api-Secret-Token check
  Providers/
    BotServiceProvider.php      # Nutgram setup + command handlers
questions/
  frontend.json
  backend.json
  qa.json
  ba.json
```

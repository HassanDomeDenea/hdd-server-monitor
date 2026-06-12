# HDD Server Monitor

Minimal PHP 8.3 endpoint uptime monitor: SQLite storage, Tailwind (CDN) dashboard behind basic auth, cron-driven checks, email notifications on down/resolved.

## Setup

```bash
composer install
cp .env.example .env   # then edit credentials, SMTP, CRON_SECRET
```

Point your web server's document root at `public/`.

## Configure endpoints

Edit `config/endpoints.json`:

```json
[
    { "name": "My API", "url": "https://api.example.com/health", "description": "Production API" }
]
```

## Cron (every minute)

Either via CLI:

```cron
* * * * * php /path/to/hdd-server-monitor/public/job.php >> /dev/null 2>&1
```

or via HTTP (uses `CRON_SECRET` from `.env`):

```cron
* * * * * curl -fsS "https://your-site/job.php?secret=YOUR_CRON_SECRET" > /dev/null
```

## How it works

- `job.php` GETs each endpoint (timeout `CHECK_TIMEOUT`, default 10s). Up = HTTP status < 400.
- `statuses` table holds the latest state per endpoint; `events` records outages (`started_at` / `resolved_at`, description `Unreachable`).
- A new outage opens one event and sends a DOWN email; recovery resolves the open event and sends a RESOLVED email. Notifications go to `NOTIFY_EMAIL`.
- Email uses SMTP via PHPMailer when `SMTP_HOST` is set, otherwise falls back to PHP `mail()`.
- Dashboard (`public/index.php`) is protected by basic auth (`APP_USERNAME` / `APP_PASSWORD`, default `admin`/`admin`) and shows current statuses plus events from the last `EVENTS_DAYS` days (default 7; ongoing events always shown).
- Timestamps are stored in UTC and displayed in `TIMEZONE` (default `Asia/Baghdad`) on the dashboard and in emails.

The SQLite database is created automatically at `data/monitor.sqlite`.

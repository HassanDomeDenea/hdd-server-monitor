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

## Docker deployment (e.g. behind nginx proxy manager)

The image bundles Apache (serving `public/`, `.htaccess` honored) and an internal
cron that runs the checks every minute — no host cron and no published ports needed.

```bash
cp .env.example .env          # then edit credentials/notifications
docker compose up -d --build
```

Notes:

- `docker-compose.yml` joins an external network (`name: npm_default` by default) —
  set it to the network your nginx proxy manager runs on (`docker network ls`).
  In NPM, create a proxy host pointing to hostname `hdd-server-monitor`, port `80`.
- `.env`, `config/endpoints.json`, and `data/` are bind-mounted: edit endpoints or
  settings on the host and they apply immediately, no rebuild. The SQLite db
  persists in `./data`.
- The last cron run's output is written to `data/last-job.log`.
- Debug tools work inside the container, e.g.
  `docker exec -it hdd-server-monitor php tools/test-telegram.php`.

## Cron (every minute, non-Docker setups)

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
- A new outage opens one event and sends a DOWN notification; recovery resolves the open event and sends a RESOLVED notification.
- Notification channels (each independently toggleable in `.env`):
  - **Email** (`MAIL_ENABLED`, default on): to `NOTIFY_EMAIL` via SMTP/PHPMailer when `SMTP_HOST` is set, otherwise PHP `mail()`. Test with `php tools/test-mail.php`.
  - **Telegram** (`TELEGRAM_ENABLED`, default off): posts to a channel via a bot. Create a bot with [@BotFather](https://t.me/BotFather), add it as an admin of your private channel, set `TELEGRAM_BOT_TOKEN`, then run `php tools/test-telegram.php` — with `TELEGRAM_CHAT_ID` empty it lists the chat ids the bot can see; fill it in and run again to send a test message.
- Dashboard (`public/index.php`) is protected by basic auth (`APP_USERNAME` / `APP_PASSWORD`, default `admin`/`admin`) and shows current statuses plus events from the last `EVENTS_DAYS` days (default 7; ongoing events always shown).
- Timestamps are stored in UTC and displayed in `TIMEZONE` (default `Asia/Baghdad`) on the dashboard and in emails.

The SQLite database is created automatically at `data/monitor.sqlite`.

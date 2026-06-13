<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

/**
 * Returns a value from .env (with optional default). Loads the file once.
 */
function env(string $key, ?string $default = null): ?string
{
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $file = BASE_PATH . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $value = trim($value);
                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
                    $value = substr($value, 1, -1);
                }
                $vars[trim($name)] = $value;
            }
        }
    }

    return $vars[$key] ?? $default;
}

/**
 * Shared PDO connection to the SQLite database, creating the schema on first use.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dir = BASE_PATH . '/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $dir . '/monitor.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS statuses (
            endpoint TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            last_update TEXT NOT NULL,
            fail_count INTEGER NOT NULL DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint TEXT NOT NULL,
            started_at TEXT NOT NULL,
            resolved_at TEXT,
            description TEXT
        )');

        // Migration: add fail_count to statuses tables created before it existed.
        $cols = $pdo->query('PRAGMA table_info(statuses)')->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('fail_count', $cols, true)) {
            $pdo->exec('ALTER TABLE statuses ADD COLUMN fail_count INTEGER NOT NULL DEFAULT 0');
        }
    }

    return $pdo;
}

/**
 * Endpoints from config/endpoints.json, keyed by URL.
 * Each entry: ['name' => ..., 'url' => ..., 'description' => ...]
 */
function endpoints(): array
{
    $file = BASE_PATH . '/config/endpoints.json';
    if (!is_file($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    $list = json_decode($raw, true);
    if (!is_array($list)) {
        return [];
    }

    $byUrl = [];
    foreach ($list as $entry) {
        if (empty($entry['url'])) {
            continue;
        }
        $byUrl[$entry['url']] = [
            'name' => $entry['name'] ?? $entry['url'],
            'url' => $entry['url'],
            'description' => $entry['description'] ?? '',
        ];
    }

    return $byUrl;
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

/**
 * Boolean .env value: true/1/yes/on (case-insensitive).
 */
function env_bool(string $key, bool $default): bool
{
    $value = env($key);
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

/**
 * Display timezone from TIMEZONE in .env (default Asia/Baghdad).
 */
function app_timezone(): DateTimeZone
{
    static $tz = null;

    if ($tz === null) {
        try {
            $tz = new DateTimeZone(env('TIMEZONE', 'Asia/Baghdad'));
        } catch (Throwable) {
            $tz = new DateTimeZone('Asia/Baghdad');
        }
    }

    return $tz;
}

/**
 * Converts a stored UTC timestamp to the display timezone.
 */
function local_time(?string $utc): string
{
    if (!$utc) {
        return '';
    }

    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
        ->setTimezone(app_timezone())
        ->format('Y-m-d H:i:s');
}

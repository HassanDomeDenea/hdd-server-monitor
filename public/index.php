<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

// --- Basic auth ---
$user = env('APP_USERNAME', 'admin');
$pass = env('APP_PASSWORD', 'admin');
$givenUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$givenPass = $_SERVER['PHP_AUTH_PW'] ?? '';
if (!hash_equals($user, $givenUser) || !hash_equals($pass, $givenPass)) {
    header('WWW-Authenticate: Basic realm="Server Monitor"');
    http_response_code(401);
    exit('Authentication required');
}

// --- Manual job trigger (POST, then redirect so a reload doesn't re-run it) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_checks'])) {
    require __DIR__ . '/../src/check.php';
    run_checks();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?ran=1');
    exit;
}

// --- Data ---
$endpoints = endpoints();

$statuses = [];
foreach (db()->query('SELECT * FROM statuses') as $row) {
    $statuses[$row['endpoint']] = $row;
}

$eventsDays = max(1, (int) env('EVENTS_DAYS', '7'));
$since = gmdate('Y-m-d H:i:s', time() - $eventsDays * 86400);
$stmt = db()->prepare(
    'SELECT * FROM events WHERE started_at >= ? OR resolved_at IS NULL ORDER BY started_at DESC'
);
$stmt->execute([$since]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$nameOf = fn (string $url): string => $endpoints[$url]['name'] ?? $url;
$e = fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

function duration(string $start, ?string $end): string
{
    $seconds = max(0, strtotime(($end ?? gmdate('Y-m-d H:i:s')) . ' UTC') - strtotime($start . ' UTC'));
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }
    return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Monitor</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <meta http-equiv="refresh" content="60">
</head>
<body class="bg-slate-100 min-h-screen text-slate-800">
<div class="max-w-5xl mx-auto px-4 py-8">
    <header class="mb-8 flex items-end justify-between">
        <div>
            <h1 class="text-2xl font-bold">Server Monitor</h1>
            <p class="text-sm text-slate-500">Auto-refreshes every minute &middot; times in <?= $e(app_timezone()->getName()) ?></p>
        </div>
        <div class="flex items-center gap-3">
            <p class="text-sm text-slate-500"><?= $e(local_time(now())) ?></p>
            <a href="<?= $e(strtok($_SERVER['REQUEST_URI'], '?')) ?>"
               class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Refresh
            </a>
            <form method="post" onsubmit="var b = this.querySelector('button'); setTimeout(function () { b.disabled = true; b.textContent = 'Checking…'; }, 0);">
                <input type="hidden" name="run_checks" value="1">
                <button type="submit"
                        class="rounded-lg bg-slate-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-60">
                    Run checks now
                </button>
            </form>
        </div>
    </header>

    <?php if (isset($_GET['ran'])): ?>
    <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        Checks completed at <?= $e(local_time(now())) ?>.
    </div>
    <?php endif; ?>

    <section class="mb-10">
        <h2 class="text-lg font-semibold mb-3">Current status</h2>
        <?php if (!$endpoints): ?>
            <p class="text-slate-500">No endpoints configured. Add some to <code>config/endpoints.json</code>.</p>
        <?php endif; ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($endpoints as $url => $ep):
                $st = $statuses[$url] ?? null;
                $state = $st['status'] ?? 'unknown';
                [$badgeClass, $dotClass, $label] = match ($state) {
                    'up' => ['bg-emerald-100 text-emerald-700', 'bg-emerald-500', 'Operational'],
                    'down' => ['bg-red-100 text-red-700', 'bg-red-500', 'Down'],
                    default => ['bg-slate-200 text-slate-600', 'bg-slate-400', 'No data'],
                };
            ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold truncate" title="<?= $e($ep['name']) ?>"><?= $e($ep['name']) ?></h3>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium <?= $badgeClass ?>">
                        <span class="h-2 w-2 rounded-full <?= $dotClass ?> <?= $state === 'down' ? 'animate-pulse' : '' ?>"></span>
                        <?= $label ?>
                    </span>
                </div>
                <p class="text-sm text-slate-500 mb-2"><?= $e($ep['description']) ?></p>
                <p class="text-xs text-slate-400 truncate" title="<?= $e($url) ?>"><?= $e($url) ?></p>
                <p class="text-xs text-slate-400 mt-2">
                    Last check: <?= $st ? $e(local_time($st['last_update'])) : 'never' ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2 class="text-lg font-semibold mb-3">Events &mdash; last <?= $eventsDays ?> day<?= $eventsDays > 1 ? 's' : '' ?></h2>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
            <?php if (!$events): ?>
                <p class="p-6 text-slate-500 text-sm">No events in the last <?= $eventsDays ?> day<?= $eventsDays > 1 ? 's' : '' ?>. 🎉</p>
            <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <th class="px-4 py-3">Endpoint</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Started</th>
                        <th class="px-4 py-3">Resolved</th>
                        <th class="px-4 py-3">Duration</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($events as $ev): $open = $ev['resolved_at'] === null; ?>
                    <tr class="<?= $open ? 'bg-red-50' : '' ?>">
                        <td class="px-4 py-3 font-medium"><?= $e($nameOf($ev['endpoint'])) ?></td>
                        <td class="px-4 py-3"><?= $e($ev['description']) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap"><?= $e(local_time($ev['started_at'])) ?></td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php if ($open): ?>
                                <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-xs font-medium">ongoing</span>
                            <?php else: ?>
                                <?= $e(local_time($ev['resolved_at'])) ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500"><?= $e(duration($ev['started_at'], $ev['resolved_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </section>
</div>
</body>
</html>

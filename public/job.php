<?php

declare(strict_types=1);

/**
 * Check job. Run from cron either way:
 *   CLI:  php /path/to/public/job.php
 *   HTTP: curl "https://your-site/job.php?secret=YOUR_CRON_SECRET"
 */

require __DIR__ . '/../src/check.php';

if (PHP_SAPI !== 'cli') {
    $secret = env('CRON_SECRET', '');
    if ($secret === '' || !hash_equals($secret, (string) ($_GET['secret'] ?? ''))) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json');
}

$results = run_checks();

if (PHP_SAPI === 'cli') {
    foreach ($results as $r) {
        printf("[%s] %s (%s) %s%s\n", $r['status'], $r['name'], $r['endpoint'], $r['detail'], $r['action'] !== 'none' ? ' -> ' . $r['action'] : '');
    }
} else {
    echo json_encode([
        'checked_at' => local_time(now()) . ' (' . app_timezone()->getName() . ')',
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

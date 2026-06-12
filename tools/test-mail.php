<?php

declare(strict_types=1);

/**
 * Sends a test notification with the full SMTP conversation printed.
 * CLI only:  php tools/test-mail.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/mailer.php';

printf(
    "To: %s\nSMTP: %s:%s (%s)\nFrom: %s\n\n",
    env('NOTIFY_EMAIL', '(not set)'),
    env('SMTP_HOST', '') ?: '(empty -> PHP mail() fallback)',
    env('SMTP_PORT', '587'),
    env('SMTP_ENCRYPTION', 'tls'),
    env('SMTP_FROM', '(not set)'),
);

$ok = send_notification(
    'Monitor test email',
    sprintf("This is a test from the server monitor.\nSent at: %s (%s)", local_time(now()), app_timezone()->getName()),
    debug: true,
);

echo $ok ? "\nRESULT: sent OK\n" : "\nRESULT: FAILED (see output above / PHP error log)\n";
exit($ok ? 0 : 1);

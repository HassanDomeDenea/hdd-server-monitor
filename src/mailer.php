<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Sends a notification email to NOTIFY_EMAIL via SMTP (PHPMailer),
 * falling back to PHP mail() when SMTP_HOST is not configured.
 * Failures are logged, never thrown — a broken mailer must not stop the checks.
 * With $debug, the full SMTP conversation is printed (for tools/test-mail.php).
 */
function send_notification(string $subject, string $body, bool $debug = false): bool
{
    $to = env("NOTIFY_EMAIL", "hassan.domedenea@gmail.com");
    if (!$to) {
        return false;
    }

    $from = env("SMTP_FROM", "monitor@localhost");
    $fromName = env("SMTP_FROM_NAME", "Server Monitor");
    $host = env("SMTP_HOST", "");

    try {
        if ($host === "" || $host === null) {
            $headers = sprintf(
                "From: %s <%s>\r\nContent-Type: text/plain; charset=UTF-8",
                $fromName,
                $from,
            );
            return @mail($to, $subject, $body, $headers);
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        if ($debug) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        $mail->Host = $host;
        $mail->Timeout = 5;
        $mail->Port = (int) env("SMTP_PORT", "587");
        $mail->SMTPAuth = env("SMTP_USERNAME", "") !== "";
        $mail->Username = env("SMTP_USERNAME", "");
        $mail->Password = env("SMTP_PASSWORD", "");
        $encryption = env("SMTP_ENCRYPTION", "tls");
        // Port 465 is implicit SSL; STARTTLS there hangs waiting for a greeting
        if ($mail->Port === 465 || $encryption === "ssl") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === "tls") {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->CharSet = "UTF-8";
        $mail->send();

        return true;
    } catch (Throwable $e) {
        error_log("[monitor] mail failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Sends a message to the Telegram channel configured in .env.
 * With $debug, the raw Bot API response is printed.
 */
function send_telegram(string $text, bool $debug = false): bool
{
    $token = env("TELEGRAM_BOT_TOKEN", "");
    $chatId = env("TELEGRAM_CHAT_ID", "");
    if ($token === "" || $chatId === "") {
        error_log("[monitor] telegram failed: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID not set");
        return false;
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(["chat_id" => $chatId, "text" => $text]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($debug) {
        echo "Telegram API response (HTTP {$code}): " . ($response === false ? $error : $response) . "\n";
    }

    if ($response === false || $code !== 200) {
        error_log("[monitor] telegram failed: HTTP {$code} " . ($response === false ? $error : $response));
        return false;
    }

    return true;
}

/**
 * Dispatches a notification to every enabled channel (email, Telegram).
 * Returns ['email' => bool, 'telegram' => bool] for the channels that ran.
 */
function notify(string $subject, string $body): array
{
    $results = [];
    if (env_bool("MAIL_ENABLED", true)) {
        $results["email"] = send_notification($subject, $body);
    }
    if (env_bool("TELEGRAM_ENABLED", false)) {
        $results["telegram"] = send_telegram($subject . "\n\n" . $body);
    }

    return $results;
}

/**
 * Human-readable summary of a notify() result for logs/job output.
 */
function describe_notify(array $results): string
{
    if (!$results) {
        return "notifications disabled";
    }

    $parts = [];
    foreach ($results as $channel => $ok) {
        $parts[] = $channel . " " . ($ok ? "sent" : "FAILED");
    }

    return implode(", ", $parts);
}

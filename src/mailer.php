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

<?php

declare(strict_types=1);

require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/mailer.php";

/**
 * Checks every configured endpoint, updates the statuses table and
 * opens/resolves events. Returns a result row per endpoint.
 */
function run_checks(): array
{
    $db = db();
    $timeout = (int) env("CHECK_TIMEOUT", "10");
    $results = [];
    $endpoints = endpoints();

    // Endpoints removed from config: drop their status rows and close any
    // open events, otherwise those events stay "ongoing" forever.
    if ($endpoints) {
        $urls = array_keys($endpoints);
        $in = implode(",", array_fill(0, count($urls), "?"));
        $db->prepare("DELETE FROM statuses WHERE endpoint NOT IN ($in)")->execute($urls);
        $db->prepare(
            "UPDATE events SET resolved_at = ?, description = description || ' (endpoint removed from config)'
            WHERE resolved_at IS NULL AND endpoint NOT IN ($in)",
        )->execute([now(), ...$urls]);
    }

    foreach ($endpoints as $url => $endpoint) {
        [$up, $detail] = check_url($url, $timeout);
        $time = now();

        $db->prepare(
            'INSERT INTO statuses (endpoint, status, last_update) VALUES (?, ?, ?)
            ON CONFLICT(endpoint) DO UPDATE SET status = excluded.status, last_update = excluded.last_update',
        )->execute([$url, $up ? "up" : "down", $time]);

        $stmt = $db->prepare(
            "SELECT * FROM events WHERE endpoint = ? AND resolved_at IS NULL ORDER BY started_at DESC LIMIT 1",
        );
        $stmt->execute([$url]);
        $openEvent = $stmt->fetch(PDO::FETCH_ASSOC);

        $action = "none";
        if (!$up && !$openEvent) {
            $db->prepare(
                "INSERT INTO events (endpoint, started_at, description) VALUES (?, ?, ?)",
            )->execute([$url, $time, "Unreachable"]);
            $sent = notify(
                sprintf("🔴 DOWN: %s", $endpoint["name"]),
                sprintf(
                    "Endpoint is unreachable.\n\nName: %s\nURL: %s\nDetail: %s\nSince: %s (%s)",
                    $endpoint["name"],
                    $url,
                    $detail,
                    local_time($time),
                    app_timezone()->getName(),
                ),
            );
            $action = "event_opened, " . describe_notify($sent);
        } elseif ($up && $openEvent) {
            $db->prepare(
                "UPDATE events SET resolved_at = ? WHERE id = ?",
            )->execute([$time, $openEvent["id"]]);
            $sent = notify(
                sprintf("🟢 RESOLVED: %s", $endpoint["name"]),
                sprintf(
                    "Endpoint is reachable again.\n\nName: %s\nURL: %s\nDown since: %s\nResolved at: %s\nTimezone: %s",
                    $endpoint["name"],
                    $url,
                    local_time($openEvent["started_at"]),
                    local_time($time),
                    app_timezone()->getName(),
                ),
            );
            $action = "event_resolved, " . describe_notify($sent);
        }

        $results[] = [
            "endpoint" => $url,
            "name" => $endpoint["name"],
            "status" => $up ? "up" : "down",
            "detail" => $detail,
            "action" => $action,
        ];
    }

    return $results;
}

/**
 * GETs the URL. Up = no transport error and HTTP status < 400.
 * Returns [bool $up, string $detail].
 */
function check_url(string $url, int $timeout): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_USERAGENT => "hdd-server-monitor/1.0",
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($errno !== 0) {
        return [false, $error ?: "cURL error " . $errno];
    }

    return [$code < 400, "HTTP " . $code];
}

<?php
declare(strict_types=1);

function mg_public_campaign_client_key(string $email): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160);
    return hash('sha256', strtolower(trim($email)) . '|' . $ip . '|' . $ua);
}

function mg_public_campaign_rate_limit(PDO $pdo, string $action, string $email, int $maxAttempts = 8, int $windowSeconds = 3600): void
{
    $key = 'public_campaign:' . $action . ':' . mg_public_campaign_client_key($email);
    if (function_exists('mg_rate_limit')) mg_rate_limit($pdo, $key, $maxAttempts, $windowSeconds);
}

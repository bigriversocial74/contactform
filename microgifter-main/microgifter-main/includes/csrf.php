<?php
/**
 * CSRF helpers for first-party forms and AJAX requests.
 */

declare(strict_types=1);

function mg_csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['mg_csrf_token'])) {
        $_SESSION['mg_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['mg_csrf_token'];
}

function mg_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(mg_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function mg_verify_csrf(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return is_string($token)
        && isset($_SESSION['mg_csrf_token'])
        && hash_equals($_SESSION['mg_csrf_token'], $token);
}

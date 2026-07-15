<?php
/**
 * Auth state helpers for server-rendered pages.
 * Raw session state is never treated as sufficient authorization; protected pages
 * use the canonical DB-backed identity/session validator when it is available.
 */
declare(strict_types=1);

function mg_current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    return isset($_SESSION['mg_user']) && is_array($_SESSION['mg_user']) ? $_SESSION['mg_user'] : null;
}

function mg_authenticated_user(bool $forceRefresh = false): ?array
{
    if (function_exists('mg_refresh_session_user')) {
        return mg_refresh_session_user($forceRefresh);
    }
    return mg_current_user();
}

function mg_is_authenticated(): bool
{
    return mg_authenticated_user() !== null;
}

function mg_user_display_name(): string
{
    $user = mg_authenticated_user();
    return $user['display_name'] ?? $user['full_name'] ?? $user['email'] ?? 'Guest';
}

function mg_safe_return_path(?string $path = null): string
{
    $candidate = $path ?? ($_SERVER['REQUEST_URI'] ?? '/');
    if ($candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//')) {
        return '/';
    }

    $parts = parse_url($candidate);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return '/';
    }

    return $candidate;
}

function mg_require_auth(string $redirect = '/signin.php', ?string $returnPath = null): array
{
    $user = mg_authenticated_user();
    if ($user !== null) {
        if (function_exists('mg_email_verification_gate_enabled')
            && mg_email_verification_gate_enabled()
            && empty($user['email_verified_at'])) {
            header('Cache-Control: no-store, private');
            header('Location: /verify-email.php?pending=1', true, 302);
            exit;
        }
        return $user;
    }

    $separator = str_contains($redirect, '?') ? '&' : '?';
    $location = $redirect . $separator . 'return=' . rawurlencode(mg_safe_return_path($returnPath));
    header('Cache-Control: no-store, private');
    header('Location: ' . $location, true, 302);
    exit;
}

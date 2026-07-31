<?php
/**
 * Auth state helpers for server-rendered pages.
 * Stage 1 uses session-backed identity. API endpoints remain responsible for DB-backed auth.
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function mg_current_user(): ?array
{
    mg_start_session();
    return isset($_SESSION['mg_user']) && is_array($_SESSION['mg_user']) ? $_SESSION['mg_user'] : null;
}

function mg_is_authenticated(): bool
{
    return mg_current_user() !== null;
}

function mg_user_display_name(): string
{
    $user = mg_current_user();
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
    $user = mg_current_user();
    if ($user !== null) {
        return $user;
    }

    $separator = str_contains($redirect, '?') ? '&' : '?';
    $location = $redirect . $separator . 'return=' . rawurlencode(mg_safe_return_path($returnPath));
    header('Cache-Control: no-store, private');
    header('Location: ' . $location, true, 302);
    exit;
}

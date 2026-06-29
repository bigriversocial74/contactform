<?php
 declare(strict_types=1);

function tcl_is_training_admin(array $user = []): bool
{
    if (!function_exists('lqr_is_authenticated') || !lqr_is_authenticated()) return false;
    $email = strtolower((string)($user['email'] ?? ''));
    if ($email === '') return false;

    $config = function_exists('lqr_config') ? lqr_config() : [];
    $training = is_array($config['training_lab'] ?? null) ? $config['training_lab'] : [];
    $admins = is_array($training['admin_emails'] ?? null) ? $training['admin_emails'] : [];
    $reviewers = is_array($training['reviewer_emails'] ?? null) ? $training['reviewer_emails'] : [];
    $allowed = array_map('strtolower', array_map('strval', array_merge($admins, $reviewers)));

    if (!$allowed) {
        $admin = is_array($config['admin'] ?? null) ? $config['admin'] : [];
        $fallback = strtolower((string)($admin['email'] ?? $config['admin_email'] ?? ''));
        if ($fallback !== '') $allowed[] = $fallback;
    }

    return $allowed ? in_array($email, $allowed, true) : false;
}

function tcl_require_training_admin(array $user = []): void
{
    if (!tcl_is_training_admin($user)) {
        http_response_code(403);
        throw new RuntimeException('Training Lab admin/reviewer access is required. Add your email to config.php training_lab.admin_emails or training_lab.reviewer_emails.');
    }
}

function tcl_can_view_participant_resource(array $user, array $participant): bool
{
    if (tcl_is_training_admin($user)) return true;
    return (string)($participant['user_id'] ?? '') === (string)($user['id'] ?? '');
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

$reference = trim((string) ($_GET['user'] ?? ''));
if ($reference === '' || strlen($reference) > 190 || strpbrk($reference, "\r\n\t") !== false) {
    header('Location: /profile.php', true, 302);
    exit;
}

$slug = '';
try {
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        "SELECT pp.slug
         FROM users u
         INNER JOIN public_profiles pp ON pp.user_id=u.id
         WHERE (u.public_id=? OR u.email=?)
           AND u.status='active'
           AND pp.status='active'
           AND pp.visibility IN ('public','unlisted')
         LIMIT 1"
    );
    $stmt->execute([$reference, $reference]);
    $slug = strtolower(trim((string) ($stmt->fetchColumn() ?: '')));
} catch (Throwable $error) {
    if (function_exists('mg_security_log')) {
        mg_security_log('warning', 'profile.user_redirect_failed', 'Unable to resolve public profile redirect.', [
            'exception_class' => $error::class,
        ]);
    }
}

$location = $slug !== ''
    ? '/profile.php?slug=' . rawurlencode($slug)
    : '/profile.php';
header('Location: ' . $location, true, 302);
exit;

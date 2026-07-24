<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/role-badges.php';

mg_require_method('GET');
mg_rate_limit('public.profile_role_badges.read', 'ip:' . mg_client_ip(), 120, 60);

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
if ($slug === '' || strlen($slug) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) !== 1) {
    mg_fail('Profile not found.', 404);
}

try {
    $pdo = mg_db();
    $stmt = $pdo->prepare(
        "SELECT r.slug
           FROM public_profiles pp
           INNER JOIN users u ON u.id = pp.user_id AND u.status = 'active'
           LEFT JOIN user_roles ur ON ur.user_id = u.id
           LEFT JOIN roles r ON r.id = ur.role_id
          WHERE pp.slug = ?
            AND pp.status = 'active'
            AND pp.visibility IN ('public','unlisted')
          ORDER BY r.slug"
    );
    $stmt->execute([$slug]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($rows === []) {
        $exists = $pdo->prepare(
            "SELECT 1
               FROM public_profiles pp
               INNER JOIN users u ON u.id = pp.user_id AND u.status = 'active'
              WHERE pp.slug = ?
                AND pp.status = 'active'
                AND pp.visibility IN ('public','unlisted')
              LIMIT 1"
        );
        $exists->execute([$slug]);
        if (!$exists->fetchColumn()) {
            mg_fail('Profile not found.', 404);
        }
    }

    $roles = array_values(array_unique(array_filter(array_map(
        static fn(mixed $role): string => strtolower(trim((string) $role)),
        $rows
    ))));

    header('Cache-Control: public, max-age=60, stale-while-revalidate=30');
    header('Vary: Cookie, Authorization');
    mg_ok([
        'roles' => $roles,
        'badges' => mg_role_badges_for_slugs($roles),
    ], 'Profile role badges loaded.');
} catch (Throwable $error) {
    mg_security_log('warning', 'public.profile_role_badges.failed', 'Unable to load public profile role badges.', [
        'exception_class' => $error::class,
        'slug' => $slug,
    ]);
    mg_fail('Unable to load profile role badges.', 500);
}

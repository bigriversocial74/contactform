<?php
declare(strict_types=1);

require_once __DIR__ . '/_public_donations_operations_projection.php';

mg_require_method('GET');
$actor = mg_admin_public_donations_require_operations_user();
$actorId = (int)$actor['id'];
mg_rate_limit('admin.public_donations_operations.read', 'user:' . $actorId, 180, 60);

$query = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);

try {
    $data = mg_admin_public_donations_read_projection(mg_db(), $actor, $query);
} catch (Throwable $error) {
    mg_fail_unexpected(
        $error,
        'admin.public_donations_operations.read_failed',
        'Unable to load Public Donations operations.',
        500,
        [],
        $actorId
    );
}

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok($data, 'Public Donations operations loaded.');

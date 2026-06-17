<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

mg_require_method('GET');
$admin = mg_require_permission('admin.users.view');

$pdo = mg_db();
$stmt = $pdo->query(
    'SELECT uma.id, uma.public_id, uma.user_id, u.email, u.display_name, um.code, um.name, uma.status, uma.requested_at, uma.reason
     FROM user_model_assignments uma
     INNER JOIN users u ON u.id = uma.user_id
     INNER JOIN user_models um ON um.id = uma.user_model_id
     WHERE uma.status = "pending"
     ORDER BY uma.requested_at DESC, uma.created_at DESC
     LIMIT 100'
);

mg_ok(['pending' => $stmt->fetchAll() ?: []], 'Pending user model requests.');

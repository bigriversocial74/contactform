<?php
declare(strict_types=1);

require_once __DIR__ . '/_engine.php';

$user = mg_require_permission('microgift.templates.manage');
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$pdo = mg_db();

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT t.public_id,t.owner_type,t.name,t.slug,t.description,t.gift_type,t.status,t.visibility,t.default_currency,t.created_at,t.updated_at,
                v.public_id AS active_version_public_id,v.version_number AS active_version_number
         FROM microgift_templates t
         LEFT JOIN microgift_template_versions v ON v.id=t.active_version_id
         WHERE t.owner_user_id=?
         ORDER BY t.updated_at DESC,t.id DESC
         LIMIT 200'
    );
    $stmt->execute([(int)$user['id']]);
    mg_ok(['templates' => $stmt->fetchAll()]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $pdo->beginTransaction();
    $result = mg_microgift_create_template($pdo, (int)$user['id'], $input);
    $pdo->commit();
    mg_audit('microgift.template_created', 'microgift_template', $result, (int)$user['id']);
    mg_event('microgift.template_created', $result, (int)$user['id']);
    mg_ok($result, 'Microgift template created.', 201);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'microgift.template_create_failed', 'Microgift template creation failed.', [], (int)$user['id']);
    mg_fail('Unable to create the Microgift template.', 500);
}

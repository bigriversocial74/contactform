<?php
declare(strict_types=1);

require_once __DIR__ . '/_engine.php';

mg_require_method('POST');
$user = mg_require_permission('microgift.templates.manage');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = trim((string)($input['action'] ?? 'create'));
$pdo = mg_db();

try {
    $pdo->beginTransaction();
    if ($action === 'create') {
        $templateId = trim((string)($input['template_id'] ?? ''));
        if ($templateId === '') throw new InvalidArgumentException('Template ID is required.');
        $result = mg_microgift_create_version($pdo, (int)$user['id'], $templateId, $input);
        $message = 'Microgift template version created.';
    } elseif ($action === 'publish') {
        $versionId = trim((string)($input['version_id'] ?? ''));
        if ($versionId === '') throw new InvalidArgumentException('Version ID is required.');
        $result = mg_microgift_publish_version($pdo, (int)$user['id'], $versionId);
        $message = 'Microgift template version published.';
    } else {
        throw new InvalidArgumentException('Invalid version action.');
    }
    $pdo->commit();
    mg_audit('microgift.template_version_' . $action, 'microgift_template_version', $result, (int)$user['id']);
    mg_event('microgift.template_version_' . $action, $result, (int)$user['id']);
    mg_ok($result, $message, $action === 'create' ? 201 : 200);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 422);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($e->getMessage(), 409);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'microgift.template_version_failed', 'Microgift template version action failed.', ['action' => $action], (int)$user['id']);
    mg_fail('Unable to process the Microgift template version.', 500);
}

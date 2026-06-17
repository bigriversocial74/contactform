<?php
declare(strict_types=1);

require_once __DIR__ . '/_engine.php';

mg_require_method('GET');
$user = mg_require_api_user();
$pdo = mg_db();
$instancePublicId = trim((string)($_GET['id'] ?? ''));

if ($instancePublicId !== '') {
    $stmt = $pdo->prepare(
        'SELECT i.public_id,i.status,i.source_type,i.source_reference,i.title_snapshot,i.description_snapshot,i.currency,i.face_value_cents,
                i.recipient_policy,i.issued_at,i.delivered_at,i.claimed_at,i.redeemed_at,i.expires_at,i.cancelled_at,i.revoked_at,
                t.public_id AS template_public_id,v.public_id AS template_version_public_id,v.version_number,
                c.public_id AS credential_public_id,c.purpose AS credential_purpose,c.status AS credential_status,c.code_last4,c.expires_at AS credential_expires_at
         FROM microgift_instances i
         INNER JOIN microgift_templates t ON t.id=i.template_id
         INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
         LEFT JOIN microgift_credentials c ON c.instance_id=i.id AND c.status IN (\'active\',\'verified\',\'locked\')
         WHERE i.public_id=? AND (i.owner_user_id=? OR i.recipient_user_id=? OR i.issuer_user_id=?)
         LIMIT 1'
    );
    $stmt->execute([$instancePublicId, (int)$user['id'], (int)$user['id'], (int)$user['id']]);
    $instance = $stmt->fetch();
    if (!$instance) mg_fail('Microgift instance not found.', 404);
    mg_ok(['instance' => $instance]);
}

$scope = trim((string)($_GET['scope'] ?? 'owned'));
$scopeColumn = match ($scope) {
    'received' => 'i.recipient_user_id',
    'issued' => 'i.issuer_user_id',
    default => 'i.owner_user_id',
};

$stmt = $pdo->prepare(
    "SELECT i.public_id,i.status,i.title_snapshot,i.currency,i.face_value_cents,i.recipient_policy,i.issued_at,i.expires_at,
            t.public_id AS template_public_id,v.public_id AS template_version_public_id,v.version_number,
            c.status AS credential_status,c.code_last4
     FROM microgift_instances i
     INNER JOIN microgift_templates t ON t.id=i.template_id
     INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
     LEFT JOIN microgift_credentials c ON c.instance_id=i.id AND c.status IN ('active','verified','locked')
     WHERE {$scopeColumn}=?
     ORDER BY i.created_at DESC,i.id DESC
     LIMIT 200"
);
$stmt->execute([(int)$user['id']]);
mg_ok(['scope' => $scope, 'instances' => $stmt->fetchAll()]);

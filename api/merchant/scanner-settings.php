<?php
declare(strict_types=1);

require_once __DIR__ . '/_claims.php';
require_once __DIR__ . '/_scanner_operations.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_require_permission('merchant.gifts.redeem');
$pdo = mg_db();
$merchantUserId = (int)$user['id'];
$workspace = mg_claim_workspace($pdo, $user);

if ($method === 'GET') {
    $settings = mg_scanner_ops_settings($pdo, $merchantUserId, (int)$workspace['id'], 0);
    mg_ok(['settings' => $settings], 'Scanner settings loaded.');
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$values = [
    'require_confirmation' => mg_scanner_ops_bool($input['require_confirmation'] ?? null, true) ? 1 : 0,
    'lock_scanner_to_location' => mg_scanner_ops_bool($input['lock_scanner_to_location'] ?? null, false) ? 1 : 0,
    'allow_manual_entry' => mg_scanner_ops_bool($input['allow_manual_entry'] ?? null, true) ? 1 : 0,
    'max_failed_scans_per_hour' => max(1, min(50, (int)($input['max_failed_scans_per_hour'] ?? 8))),
    'require_manager_review_high_risk' => mg_scanner_ops_bool($input['require_manager_review_high_risk'] ?? null, true) ? 1 : 0,
    'high_risk_threshold' => max(10, min(100, (int)($input['high_risk_threshold'] ?? 65))),
];
$stmt = $pdo->prepare('SELECT id FROM merchant_scanner_settings WHERE merchant_user_id=? AND workspace_id=? AND location_id IS NULL LIMIT 1');
$stmt->execute([$merchantUserId, (int)$workspace['id']]);
$id = (int)($stmt->fetchColumn() ?: 0);
if ($id > 0) {
    $pdo->prepare('UPDATE merchant_scanner_settings SET require_confirmation=?,lock_scanner_to_location=?,allow_manual_entry=?,max_failed_scans_per_hour=?,require_manager_review_high_risk=?,high_risk_threshold=?,updated_at=NOW() WHERE id=?')->execute([$values['require_confirmation'],$values['lock_scanner_to_location'],$values['allow_manual_entry'],$values['max_failed_scans_per_hour'],$values['require_manager_review_high_risk'],$values['high_risk_threshold'],$id]);
} else {
    $pdo->prepare('INSERT INTO merchant_scanner_settings (public_id,merchant_user_id,workspace_id,require_confirmation,lock_scanner_to_location,allow_manual_entry,max_failed_scans_per_hour,require_manager_review_high_risk,high_risk_threshold,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())')->execute([mg_public_uuid(),$merchantUserId,(int)$workspace['id'],$values['require_confirmation'],$values['lock_scanner_to_location'],$values['allow_manual_entry'],$values['max_failed_scans_per_hour'],$values['require_manager_review_high_risk'],$values['high_risk_threshold']]);
}
mg_ok(['settings' => $values], 'Scanner settings saved.');

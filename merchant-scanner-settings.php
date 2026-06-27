<?php
declare(strict_types=1);
require_once __DIR__ . '/api/bootstrap.php';
$user = mg_require_permission('merchant.gifts.redeem');
$pdo = mg_db();
$workspace = mg_claim_workspace($pdo, $user);
$stmt = $pdo->prepare('SELECT * FROM merchant_scanner_settings WHERE merchant_user_id=? AND workspace_id=? AND location_id IS NULL LIMIT 1');
$stmt->execute([(int)$user['id'], (int)$workspace['id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['require_confirmation'=>1,'lock_scanner_to_location'=>0,'allow_manual_entry'=>1,'max_failed_scans_per_hour'=>8,'require_manager_review_high_risk'=>1,'high_risk_threshold'=>65];
$devicesStmt = $pdo->prepare('SELECT d.*, ml.name location_name FROM scanner_device_sessions d LEFT JOIN merchant_locations ml ON ml.id=d.location_id WHERE d.merchant_user_id=? ORDER BY d.last_scan_at DESC, d.created_at DESC LIMIT 50');
$devicesStmt->execute([(int)$user['id']]);
$devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);
$page_title='Scanner Settings | Microgifter'; $page_section='agent'; $header_mode='agent';
require __DIR__ . '/includes/header.php';
?>
<main class="mg-app-shell" style="padding:34px 18px;background:#f8fafc;min-height:70vh"><aside class="mg-app-sidebar" hidden></aside><section style="max-width:1100px;margin:0 auto;display:grid;gap:18px"><h1>Scanner Settings</h1><p>Manage scanner rules and known scanner devices.</p><form method="post" action="/api/merchant/scanner-settings.php" style="display:grid;gap:12px;background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:20px"><input type="hidden" name="csrf_token" value="<?= mg_e(mg_csrf_token()) ?>"><label><input type="checkbox" name="require_confirmation" value="1" <?= !empty($settings['require_confirmation'])?'checked':'' ?>> Require confirmation</label><label><input type="checkbox" name="allow_manual_entry" value="1" <?= !empty($settings['allow_manual_entry'])?'checked':'' ?>> Allow manual entry</label><label>Max issues per hour <input type="number" name="max_failed_scans_per_hour" min="1" max="50" value="<?= mg_e((string)$settings['max_failed_scans_per_hour']) ?>"></label><label>High-risk threshold <input type="number" name="high_risk_threshold" min="10" max="100" value="<?= mg_e((string)$settings['high_risk_threshold']) ?>"></label><button type="submit">Save settings</button></form><section style="background:#fff;border:1px solid #e2e8f0;border-radius:22px;padding:20px"><h2>Known scanner devices</h2><?php foreach($devices as $device): ?><p><strong><?= mg_e((string)$device['device_label']) ?></strong><br><?= mg_e((string)$device['public_id']) ?> · <?= mg_e((string)$device['status']) ?> · <?= mg_e((string)($device['location_name'] ?? '')) ?></p><?php endforeach; ?><?php if(!$devices): ?><p>No scanner devices yet.</p><?php endif; ?></section></section></main>
<?php require __DIR__ . '/includes/footer.php'; ?>
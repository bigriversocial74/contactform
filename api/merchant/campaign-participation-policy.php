<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';
require_once dirname(__DIR__, 2) . '/api/public/campaigns/_participation_policy.php';

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignId = strtolower(trim((string)($input['campaign_id'] ?? $input['id'] ?? '')));
$mode = strtolower(trim((string)($input['participation_policy'] ?? '')));
if (strlen($campaignId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $campaignId) !== 1 || !in_array($mode, ['email_only','account_recommended','account_required'], true)) mg_fail('Invalid campaign participation policy.', 422);
$stmt = $pdo->prepare('SELECT id,campaign_type,rules_json FROM campaigns WHERE public_id=? AND merchant_user_id=? LIMIT 1');
$stmt->execute([$campaignId, $merchantId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) mg_fail('Campaign not found.', 404);
$rules = json_decode((string)($row['rules_json'] ?? ''), true);
if (!is_array($rules)) $rules = [];
$rules['campaign_type'] = (string)($row['campaign_type'] ?? ($rules['campaign_type'] ?? ''));
$rules['participation_policy'] = $mode;
$rules['version'] = max(3, (int)($rules['version'] ?? 1));
$rules['registry'] = (string)($rules['registry'] ?? 'campaign_types_v3_participation_policy');
$pdo->prepare('UPDATE campaigns SET rules_json=?, updated_at=NOW() WHERE id=? AND merchant_user_id=?')->execute([json_encode($rules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), (int)$row['id'], $merchantId]);
mg_ok(['campaign_id' => $campaignId, 'participation_policy' => $mode, 'rules' => $rules], 'Campaign participation policy saved.');

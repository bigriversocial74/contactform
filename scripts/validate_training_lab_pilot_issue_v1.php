<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $path): string => is_file($root . '/' . $path) ? (file_get_contents($root . '/' . $path) ?: '') : '';
$exists = static fn(string $path): bool => is_file($root . '/' . $path);
$failures = [];
$check = static function (bool $condition, string $label) use (&$failures): void { if (!$condition) $failures[] = $label; };
$servicePath = 'api/integrations/_training_lab_reward_pilot_issue.php';
$routePath = 'api/integrations/training-lab-reward-pilot-issue.php';
$service = $read($servicePath);
$route = $read($routePath);
$check($exists($servicePath) && $exists($routePath), 'pilot issue service and route exist');
$check(str_contains($service, 'MG_TRAINING_LAB_PILOT_ISSUE_ENABLED') && str_contains($service, "'enabled'=>mg_training_lab_pilot_issue_bool"), 'endpoint is disabled by default');
$check(str_contains($service, 'training-lab-reward-issue-v1\\n') && str_contains($service, "hash_hmac('sha256'"), 'versioned HMAC contract exists');
$check(str_contains($service, 'hash_equals($expected, $signature)') && str_contains($service, "'signature_invalid'"), 'constant-time signature verification exists');
$check(str_contains($service, "'timestamp_expired'") && str_contains($service, "'request_replayed'"), 'timestamp and nonce replay protection exist');
$check(str_contains($service, "'training_lab_reward_issue_pilot_v1'") && str_contains($service, "'pilot_only'=>true") && str_contains($service, "'readback_required'=>true"), 'pilot-only request contract is enforced');
$check(str_contains($service, 'merchant_workspaces') && str_contains($service, 'merchant_user_id') && str_contains($service, 'merchant_context_invalid'), 'merchant issuer is resolved server-side');
$check(str_contains($service, 'microgift_template_versions') && str_contains($service, "v.status='published'") && str_contains($service, "t.status='active'"), 'published merchant-owned template is required');
$check(str_contains($service, 'template_value_mismatch') && str_contains($service, 'template_currency_mismatch') && str_contains($service, 'MG_TRAINING_LAB_PILOT_ISSUE_MAX_VALUE_CENTS'), 'value and currency boundaries are enforced');
$check(str_contains($service, 'mg_microgift_issue($pdo') && str_contains($service, "'source_type'=>'merchant'") && str_contains($service, "'idempotency_key'=>"), 'canonical idempotent Microgift engine is used');
$check(str_contains($service, 'mg_action_center_sent($pdo') && str_contains($service, 'recipient_action_center_item_id'), 'Action Center delivery is projected');
$check(str_contains($service, 'recipient_idempotency_conflict') && str_contains($service, 'idempotency_binding_invalid'), 'idempotent recipient binding is verified');
$check(str_contains($service, 'raw_identity_secret_signature_nonce_and_payload_excluded') && !str_contains($route, "'exception_message'=>"), 'audit and errors exclude sensitive material');
$check(str_contains($route, "mg_require_method('POST')") && str_contains($route, 'application/json') && str_contains($route, 'mg_training_lab_pilot_issue_authenticate'), 'route is POST-only signed JSON');
$check(!is_file($root . '/database/stage896_training_lab_pilot_issue.sql'), 'no Stage 896 SQL migration exists');
if ($failures) { fwrite(STDERR, "Stage 896 Microgifter pilot issue validation failed:\n- " . implode("\n- ", $failures) . "\n"); exit(1); }
echo "Stage 896 Microgifter pilot issue validation passed.\n";

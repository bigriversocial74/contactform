<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';

function mg_lqsc_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

mg_require_method('POST');
$user = mg_merchant_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$minutes = max(1, min(60, (int)($input['expires_minutes'] ?? 15)));
if (strlen($campaignId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $campaignId) !== 1) mg_fail('Invalid Loyalty Quest.', 422);

$stmt = $pdo->prepare("SELECT public_id,public_slug,title,status,qr_code_token,rules_json FROM campaigns WHERE public_id=? AND merchant_user_id=? AND campaign_type='loyalty_quest' LIMIT 1");
$stmt->execute([$campaignId, $merchantId]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$campaign) mg_fail('Loyalty Quest not found.', 404);
if ((string)$campaign['status'] !== 'active') mg_fail('Activate this Loyalty Quest before generating completion codes.', 409);
$rules = json_decode((string)($campaign['rules_json'] ?? ''), true);
if (!is_array($rules)) $rules = [];
if ((string)($rules['verification_type'] ?? '') !== 'signed_qr') mg_fail('This Loyalty Quest does not use signed QR verification.', 409);
$secret = (string)($campaign['qr_code_token'] ?? '');
if ($secret === '') mg_fail('Signed QR verification is not configured.', 409);

$issuedAt = time();
$expiresAt = $issuedAt + ($minutes * 60);
$payload = [
    'v'=>1,
    'campaign'=>(string)$campaign['public_id'],
    'iat'=>$issuedAt,
    'exp'=>$expiresAt,
];
$encoded = mg_lqsc_base64url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
$nonce = mg_lqsc_base64url(random_bytes(18));
$signature = hash_hmac('sha256', $encoded . '.' . $nonce, $secret);
$code = $encoded . '.' . $nonce . '.' . $signature;
mg_audit('merchant.loyalty_quest_signed_code_created', 'campaign', ['campaign_id'=>$campaignId,'expires_at'=>gmdate('c', $expiresAt)], $merchantId);
mg_ok([
    'campaign_id'=>$campaignId,
    'campaign_title'=>(string)$campaign['title'],
    'code'=>$code,
    'expires_at'=>gmdate('c', $expiresAt),
    'expires_minutes'=>$minutes,
    'single_use'=>true,
], 'Single-use signed completion code generated.', 201);

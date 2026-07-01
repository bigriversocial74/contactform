<?php
declare(strict_types=1);

function mg_claim_voucher_secret(): string
{
    if (function_exists('mg_claim_code_pepper')) {
        return mg_claim_code_pepper();
    }
    $config = require dirname(__DIR__) . '/config.php';
    $secret = trim((string)($config['security']['claim_code_pepper'] ?? ''));
    if ($secret !== '') return $secret;
    throw new RuntimeException('Claim voucher security is not configured.');
}

function mg_claim_voucher_token_hash(string $token): string
{
    return hash_hmac('sha256', trim($token), mg_claim_voucher_secret());
}

function mg_claim_voucher_request_hashes(): array
{
    $pepper = mg_claim_voucher_secret();
    return [
        'ip_hash' => hash_hmac('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''), $pepper),
        'user_agent_hash' => hash_hmac('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), $pepper),
    ];
}

function mg_claim_voucher_parse_token(string $token): array
{
    $token = trim($token);
    if (!preg_match('/^mgv1_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_([a-f0-9]{32})$/i', $token, $match)) {
        throw new RuntimeException('Invalid claim voucher token.');
    }
    return ['public_id' => strtolower($match[1]), 'secret' => strtolower($match[2]), 'token' => $token];
}

function mg_claim_voucher_scan_payload(string $token): string
{
    return 'MGFT-CLAIM-TOKEN|' . trim($token);
}

function mg_claim_voucher_issue_token(PDO $pdo, int $actionItemId, string $actionItemPublicId, int $instanceId, int $userId, int $ttlSeconds = 900): array
{
    if ($actionItemId < 1 || $instanceId < 1 || $userId < 1 || trim($actionItemPublicId) === '') {
        throw new InvalidArgumentException('Invalid voucher token request.');
    }
    $publicId = mg_public_uuid();
    $secret = bin2hex(random_bytes(16));
    $token = 'mgv1_' . $publicId . '_' . $secret;
    $tokenHash = mg_claim_voucher_token_hash($token);
    $ttlSeconds = max(60, min($ttlSeconds, 3600));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $request = mg_claim_voucher_request_hashes();

    $pdo->prepare("UPDATE claim_voucher_tokens SET status='expired',updated_at=NOW() WHERE action_item_id=? AND user_id=? AND status IN ('issued','scanned') AND expires_at<NOW()")->execute([$actionItemId, $userId]);
    $pdo->prepare("INSERT INTO claim_voucher_tokens (public_id,action_item_id,instance_id,user_id,token_hash,status,expires_at,created_ip_hash,created_user_agent_hash,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'issued',?,?,?,?,NOW(),NOW())")->execute([
        $publicId,
        $actionItemId,
        $instanceId,
        $userId,
        $tokenHash,
        $expiresAt,
        $request['ip_hash'],
        $request['user_agent_hash'],
        json_encode(['action_item_public_id' => $actionItemPublicId, 'token_version' => 1], JSON_UNESCAPED_SLASHES),
    ]);

    return [
        'public_id' => $publicId,
        'token' => $token,
        'token_hash' => $tokenHash,
        'scan_payload' => mg_claim_voucher_scan_payload($token),
        'expires_at_sql' => $expiresAt,
        'expires_at' => date(DATE_ATOM, strtotime($expiresAt)),
    ];
}

function mg_claim_voucher_load_token(PDO $pdo, string $token, bool $forUpdate = false): array
{
    $parsed = mg_claim_voucher_parse_token($token);
    $sql = "SELECT cvt.*, ac.public_id action_item_public_id, ac.folder action_item_folder, ac.state action_item_state, ac.archived_at action_item_archived_at,
                   mi.public_id instance_public_id, mi.status instance_status, mi.expires_at instance_expires_at,
                   t.name template_name
            FROM claim_voucher_tokens cvt
            INNER JOIN microgift_inbox_items ac ON ac.id=cvt.action_item_id
            INNER JOIN microgift_instances mi ON mi.id=cvt.instance_id
            INNER JOIN microgift_templates t ON t.id=mi.template_id
            WHERE cvt.public_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parsed['public_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals((string)$row['token_hash'], mg_claim_voucher_token_hash($token))) {
        throw new RuntimeException('Invalid claim voucher token.');
    }
    $row['raw_token'] = $token;
    return $row;
}

function mg_claim_voucher_require_active(PDO $pdo, string $token, bool $forUpdate = false): array
{
    $row = mg_claim_voucher_load_token($pdo, $token, $forUpdate);
    $status = (string)($row['status'] ?? '');
    if ($status === 'redeemed') {
        throw new RuntimeException('Voucher QR token already redeemed.');
    }
    if (in_array($status, ['revoked','expired'], true)) {
        throw new RuntimeException('Voucher QR token is no longer active.');
    }
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
        if ($forUpdate) {
            $pdo->prepare("UPDATE claim_voucher_tokens SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        }
        throw new RuntimeException('Voucher QR token has expired.');
    }
    if (!empty($row['action_item_archived_at'])) {
        throw new RuntimeException('Voucher is archived and cannot be scanned.');
    }
    if (in_array((string)($row['instance_status'] ?? ''), ['cancelled','revoked','expired'], true)) {
        throw new RuntimeException('Voucher is not available for merchant scan.');
    }
    return $row;
}

function mg_claim_voucher_mark_scanned(PDO $pdo, int $tokenId, int $scannerUserId, int $scannerLocationId): void
{
    if ($tokenId < 1) return;
    $pdo->prepare("UPDATE claim_voucher_tokens SET status=IF(status='issued','scanned',status),first_scanned_at=COALESCE(first_scanned_at,NOW()),last_scanned_at=NOW(),scan_count=scan_count+1,scanner_user_id=?,scanner_location_id=?,updated_at=NOW() WHERE id=? AND status IN ('issued','scanned')")->execute([$scannerUserId, $scannerLocationId, $tokenId]);
}

function mg_claim_voucher_mark_redeemed(PDO $pdo, int $tokenId, int $scannerUserId, int $scannerLocationId): void
{
    if ($tokenId < 1) return;
    $pdo->prepare("UPDATE claim_voucher_tokens SET status='redeemed',redeemed_at=COALESCE(redeemed_at,NOW()),last_scanned_at=COALESCE(last_scanned_at,NOW()),scanner_user_id=?,scanner_location_id=?,updated_at=NOW() WHERE id=? AND status IN ('issued','scanned')")->execute([$scannerUserId, $scannerLocationId, $tokenId]);
}

function mg_wallet_claim_voucher_parse_token(string $token): array
{
    $token = trim($token);
    if (!preg_match('/^mgwv1_([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})_([a-f0-9]{32})$/i', $token, $match)) {
        throw new RuntimeException('Invalid wallet claim voucher token.');
    }
    return ['public_id' => strtolower($match[1]), 'secret' => strtolower($match[2]), 'token' => $token];
}

function mg_wallet_claim_voucher_scan_payload(string $token): string
{
    return 'MGFT-WALLET-CLAIM-TOKEN|' . trim($token);
}

function mg_wallet_claim_voucher_issue_token(PDO $pdo, int $walletItemId, string $walletPublicId, int $userId, int $merchantUserId, int $ttlSeconds = 900): array
{
    if ($walletItemId < 1 || $userId < 1 || $merchantUserId < 1 || trim($walletPublicId) === '') {
        throw new InvalidArgumentException('Invalid wallet voucher token request.');
    }
    $publicId = mg_public_uuid();
    $secret = bin2hex(random_bytes(16));
    $token = 'mgwv1_' . $publicId . '_' . $secret;
    $tokenHash = mg_claim_voucher_token_hash($token);
    $ttlSeconds = max(60, min($ttlSeconds, 3600));
    $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $request = mg_claim_voucher_request_hashes();

    $pdo->prepare("UPDATE wallet_claim_voucher_tokens SET status='expired',updated_at=NOW() WHERE wallet_item_id=? AND user_id=? AND status IN ('issued','scanned') AND expires_at<NOW()")->execute([$walletItemId, $userId]);
    $pdo->prepare("INSERT INTO wallet_claim_voucher_tokens (public_id,wallet_item_id,user_id,merchant_user_id,token_hash,status,expires_at,created_ip_hash,created_user_agent_hash,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,'issued',?,?,?,?,NOW(),NOW())")->execute([
        $publicId,
        $walletItemId,
        $userId,
        $merchantUserId,
        $tokenHash,
        $expiresAt,
        $request['ip_hash'],
        $request['user_agent_hash'],
        json_encode(['wallet_item_public_id' => $walletPublicId, 'action_item_public_id' => 'wallet-' . $walletPublicId, 'token_version' => 1], JSON_UNESCAPED_SLASHES),
    ]);

    return [
        'public_id' => $publicId,
        'token' => $token,
        'token_hash' => $tokenHash,
        'scan_payload' => mg_wallet_claim_voucher_scan_payload($token),
        'expires_at_sql' => $expiresAt,
        'expires_at' => date(DATE_ATOM, strtotime($expiresAt)),
    ];
}

function mg_wallet_claim_voucher_load_token(PDO $pdo, string $token, bool $forUpdate = false): array
{
    $parsed = mg_wallet_claim_voucher_parse_token($token);
    $sql = "SELECT wcvt.*, wi.public_id wallet_item_public_id, wi.status wallet_item_status, wi.expires_at wallet_item_expires_at,
                   wi.user_id wallet_user_id, wi.merchant_user_id wallet_merchant_user_id
            FROM wallet_claim_voucher_tokens wcvt
            INNER JOIN wallet_items wi ON wi.id=wcvt.wallet_item_id
            WHERE wcvt.public_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$parsed['public_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !hash_equals((string)$row['token_hash'], mg_claim_voucher_token_hash($token))) {
        throw new RuntimeException('Invalid wallet claim voucher token.');
    }
    $row['raw_token'] = $token;
    return $row;
}

function mg_wallet_claim_voucher_require_active(PDO $pdo, string $token, bool $forUpdate = false): array
{
    $row = mg_wallet_claim_voucher_load_token($pdo, $token, $forUpdate);
    $status = (string)($row['status'] ?? '');
    if ($status === 'redeemed') {
        throw new RuntimeException('Wallet voucher QR token already redeemed.');
    }
    if (in_array($status, ['revoked','expired'], true)) {
        throw new RuntimeException('Wallet voucher QR token is no longer active.');
    }
    if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) {
        if ($forUpdate) {
            $pdo->prepare("UPDATE wallet_claim_voucher_tokens SET status='expired',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);
        }
        throw new RuntimeException('Wallet voucher QR token has expired.');
    }
    if (!empty($row['wallet_item_expires_at']) && strtotime((string)$row['wallet_item_expires_at']) < time() && !in_array((string)($row['wallet_item_status'] ?? ''), ['redeemed','cancelled'], true)) {
        throw new RuntimeException('Wallet reward has expired.');
    }
    if (in_array((string)($row['wallet_item_status'] ?? ''), ['cancelled','expired'], true)) {
        throw new RuntimeException('Wallet reward is not available for merchant scan.');
    }
    return $row;
}

function mg_wallet_claim_voucher_mark_scanned(PDO $pdo, int $tokenId, int $scannerUserId, int $scannerLocationId): void
{
    if ($tokenId < 1) return;
    $pdo->prepare("UPDATE wallet_claim_voucher_tokens SET status=IF(status='issued','scanned',status),first_scanned_at=COALESCE(first_scanned_at,NOW()),last_scanned_at=NOW(),scan_count=scan_count+1,scanner_user_id=?,scanner_location_id=?,updated_at=NOW() WHERE id=? AND status IN ('issued','scanned')")->execute([$scannerUserId, $scannerLocationId, $tokenId]);
}

function mg_wallet_claim_voucher_mark_redeemed(PDO $pdo, int $tokenId, int $scannerUserId, int $scannerLocationId): void
{
    if ($tokenId < 1) return;
    $pdo->prepare("UPDATE wallet_claim_voucher_tokens SET status='redeemed',redeemed_at=COALESCE(redeemed_at,NOW()),last_scanned_at=COALESCE(last_scanned_at,NOW()),scanner_user_id=?,scanner_location_id=?,updated_at=NOW() WHERE id=? AND status IN ('issued','scanned')")->execute([$scannerUserId, $scannerLocationId, $tokenId]);
}
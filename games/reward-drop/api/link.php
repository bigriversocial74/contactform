<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

rd_require_post();
$user = rd_require_user_json();
$input = rd_input();
rd_require_csrf($input);
$config = rd_config();
$pdo = mg_db();
$readiness = rd_readiness($pdo, $config);

if (!$readiness['configured'] || !$readiness['credential_found'] || !$readiness['scopes_ready']) {
    rd_json(['ok' => false, 'message' => 'Reward Drop API setup is incomplete.'], 503);
}

$appId = (int)$readiness['app_id'];
$userId = (int)$user['id'];
$existing = rd_active_link($pdo, $appId, $userId);
if ($existing !== null) {
    rd_json(['ok' => true, 'linked' => true, 'linked_account_id' => (string)$existing['public_id']]);
}

$externalUserId = rd_external_user_id($userId);
$state = rd_state_create($userId, $externalUserId, $config);
$returnUrl = rtrim((string)$config['public_url'], '/') . '/';

try {
    $response = rd_api_request($config, 'POST', '/api/public/v1/account-links/start.php', [
        'external_user_id' => $externalUserId,
        'return_url' => $returnUrl,
        'state' => $state,
        'metadata' => [
            'source' => 'reward-drop-game',
            'microgifter_user_id' => $userId,
        ],
    ], [
        'X-Request-ID: reward-drop-link-' . $userId . '-' . bin2hex(random_bytes(6)),
    ]);
    $linkUrl = trim((string)($response['data']['link_url'] ?? ''));
    if ($linkUrl === '') throw new RuntimeException('The account-link URL was not returned.');
    rd_json(['ok' => true, 'linked' => false, 'link_url' => $linkUrl]);
} catch (Throwable $error) {
    rd_json(['ok' => false, 'message' => $error->getMessage()], 502);
}

<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'index' => 'games/reward-drop/index.php',
    'bootstrap' => 'games/reward-drop/includes/bootstrap.php',
    'link' => 'games/reward-drop/api/link.php',
    'start' => 'games/reward-drop/api/start.php',
    'complete' => 'games/reward-drop/api/complete.php',
    'status' => 'games/reward-drop/api/status.php',
    'webhook' => 'games/reward-drop/webhook.php',
    'js' => 'games/reward-drop/assets/game.js',
    'css' => 'games/reward-drop/assets/game.css',
    'readme' => 'games/reward-drop/README.md',
    'sql' => 'database/reward_drop_game_v1.sql',
    'manifest' => 'config/migrations.php',
    'distribution' => 'includes/merchant-distribution-view.php',
    'distribution_page' => 'merchant-distribution.php',
    'distribution_css' => 'assets/css/merchant-distribution-game.css',
];

$files = [];
foreach ($paths as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing Reward Drop file: {$relative}\n");
        exit(1);
    }
    $files[$key] = (string)file_get_contents($path);
}

$checks = [
    'game reuses the canonical Microgifter application session' =>
        str_contains($files['bootstrap'], "'/includes/app.php'")
        && str_contains($files['bootstrap'], 'mg_current_user()')
        && str_contains($files['index'], '/signin.php?return='),
    'game introduces no local password or duplicate authentication store' =>
        !str_contains($files['sql'], 'password')
        && !str_contains($files['index'], 'type="password"')
        && !str_contains($files['bootstrap'], 'password_hash'),
    'browser configuration excludes the API key and webhook secret' =>
        str_contains($files['index'], '$safeClientConfig')
        && !str_contains($files['js'], 'MG_REWARD_DROP_API_KEY')
        && !str_contains($files['js'], 'webhook_secret')
        && !str_contains($files['index'], "'api_key' =>")
        && !str_contains($files['index'], "'webhook_secret' =>"),
    'server-side API client authenticates with a bearer credential' =>
        str_contains($files['bootstrap'], "'Authorization: Bearer '")
        && str_contains($files['bootstrap'], 'CURLOPT_TIMEOUT')
        && str_contains($files['bootstrap'], 'CURLOPT_FOLLOWLOCATION => false'),
    'account-link start uses signed expiring state and the canonical endpoint' =>
        str_contains($files['bootstrap'], 'rd_state_create')
        && str_contains($files['bootstrap'], 'hash_hmac')
        && str_contains($files['bootstrap'], "'exp' => time() + 1800")
        && str_contains($files['link'], '/api/public/v1/account-links/start.php'),
    'account-link state is verified against the signed-in user' =>
        str_contains($files['index'], 'rd_state_verify')
        && str_contains($files['bootstrap'], "(int)(\$payload['uid'] ?? 0) !== \$userId"),
    'first-party endpoints enforce POST and CSRF for writes' =>
        substr_count($files['link'], 'rd_require_post()') === 1
        && substr_count($files['start'], 'rd_require_post()') === 1
        && substr_count($files['complete'], 'rd_require_post()') === 1
        && str_contains($files['link'], 'rd_require_csrf')
        && str_contains($files['start'], 'rd_require_csrf')
        && str_contains($files['complete'], 'rd_require_csrf'),
    'game runs use random opaque tokens and store only SHA-256 hashes' =>
        str_contains($files['start'], 'random_bytes(32)')
        && str_contains($files['start'], "hash('sha256', \$runToken)")
        && str_contains($files['complete'], "hash_equals((string)\$run['run_token_hash']"),
    'game result validation enforces ownership expiration duration and target' =>
        str_contains($files['complete'], 'public_id=? AND user_id=?')
        && str_contains($files['complete'], "strtotime((string)\$run['expires_at']) < time()")
        && str_contains($files['complete'], "\$elapsed < (int)\$config['minimum_play_seconds']")
        && str_contains($files['complete'], "(int)\$score < (int)\$run['target_score']"),
    'reward cooldown is enforced server side' =>
        str_contains($files['bootstrap'], 'function rd_cooldown')
        && str_contains($files['start'], 'rd_cooldown')
        && str_contains($files['start'], "if (!\$cooldown['eligible'])"),
    'reward issue uses the live Public Distribution API contract' =>
        str_contains($files['complete'], '/api/public/v1/rewards/issue.php')
        && str_contains($files['complete'], "'program_id'")
        && str_contains($files['complete'], "'linked_account_id'")
        && str_contains($files['complete'], "'template_id'")
        && str_contains($files['complete'], "'game.reward.earned'"),
    'reward issue has request and idempotency headers' =>
        str_contains($files['complete'], 'X-Request-ID: reward-drop-')
        && str_contains($files['complete'], 'X-Idempotency-Key: ')
        && str_contains($files['sql'], 'UNIQUE KEY uq_reward_drop_runs_external_event'),
    'duplicate completion returns the existing reward instead of issuing twice' =>
        str_contains($files['complete'], "in_array((string)\$run['status'], ['queued','delivered'], true)")
        && str_contains($files['complete'], "'duplicate' => true"),
    'only live app and live key configurations can start reward runs' =>
        str_contains($files['start'], "!\$readiness['app_live']")
        && str_contains($files['start'], "!\$readiness['key_live']")
        && str_contains($files['bootstrap'], "'distribution:rewards.issue'")
        && str_contains($files['bootstrap'], "'distribution:rewards.status'"),
    'webhook verifies timestamped HMAC signatures with constant-time comparison' =>
        str_contains($files['webhook'], "hash_hmac('sha256', \$timestamp . '.' . \$body")
        && str_contains($files['webhook'], 'abs(time() - (int)$timestamp) <= 300')
        && str_contains($files['webhook'], 'hash_equals($expected, $signature)'),
    'webhook receipts deduplicate event and delivery identifiers' =>
        str_contains($files['webhook'], 'INSERT IGNORE INTO reward_drop_webhook_receipts')
        && str_contains($files['sql'], 'UNIQUE KEY uq_reward_drop_webhook_event')
        && str_contains($files['sql'], 'UNIQUE KEY uq_reward_drop_webhook_delivery'),
    'webhook maps queued delivered and failed reward lifecycle states' =>
        str_contains($files['webhook'], "['reward.delivered','reward.issued','reward.completed']")
        && str_contains($files['webhook'], "['reward.failed','reward.cancelled','reward.expired']")
        && str_contains($files['webhook'], "['reward.queued','reward.processing']"),
    'status endpoint is user scoped and returns the canonical Inbox URL' =>
        str_contains($files['status'], 'rd_find_run($pdo, $runId, (int)$user')
        && str_contains($files['bootstrap'], "'inbox_url' => '/inbox.php'"),
    'playable browser game contains score timer touch buttons and status polling' =>
        str_contains($files['js'], 'spawnGift')
        && str_contains($files['js'], 'data-rd-score')
        && str_contains($files['js'], 'remainingSeconds')
        && str_contains($files['js'], 'pollStatus')
        && str_contains($files['index'], 'data-rd-arena'),
    'game UI supports mobile and reduced motion' =>
        str_contains($files['css'], '@media(max-width:620px)')
        && str_contains($files['css'], '@media(prefers-reduced-motion:reduce)'),
    'runtime schema links runs to canonical users and developer apps' =>
        str_contains($files['sql'], 'CONSTRAINT fk_reward_drop_runs_user')
        && str_contains($files['sql'], 'CONSTRAINT fk_reward_drop_runs_app')
        && str_contains($files['sql'], 'run_token_hash CHAR(64)')
        && str_contains($files['sql'], 'reward_public_id CHAR(36)'),
    'Reward Drop migration is registered in the canonical manifest' =>
        str_contains($files['manifest'], "'reward_drop_game_v1.sql'"),
    'Distribution page exposes the game workspace and live readiness checks' =>
        str_contains($files['distribution'], 'id="distribution-game"')
        && str_contains($files['distribution'], '/games/reward-drop/')
        && (str_contains($files['distribution'], 'Game Integration') || str_contains($files['distribution'], 'Legacy example game'))
        && str_contains($files['distribution'], '/merchant-games.php')
        && str_contains($files['distribution'], 'rd_readiness'),
    'Distribution page loads the scoped game panel stylesheet' =>
        str_contains($files['distribution_page'], '/assets/css/merchant-distribution-game.css?v=1.0.0')
        && str_contains($files['distribution_css'], '.mg-game-integration'),
    'deployment documentation includes SQL environment scopes webhook and QA' =>
        str_contains($files['readme'], 'database/reward_drop_game_v1.sql')
        && str_contains($files['readme'], 'MG_REWARD_DROP_API_KEY')
        && str_contains($files['readme'], 'distribution:rewards.issue')
        && str_contains($files['readme'], '/games/reward-drop/webhook.php')
        && str_contains($files['readme'], 'Manual QA'),
];

$failed = [];
foreach ($checks as $label => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed[] = $label;
}

if ($failed !== []) {
    fwrite(STDERR, 'Reward Drop game validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}

echo 'Reward Drop game contract: ' . count($checks) . '/' . count($checks) . " checks passed.\n";

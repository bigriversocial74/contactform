<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';
lqr_boot_session();
lqr_auto_csrf_output();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        lqr_require_csrf();
    } catch (Throwable $e) {
        http_response_code(419);
        echo '<!doctype html><meta charset="utf-8"><title>Security token expired</title><body style="font-family:Arial,sans-serif;background:#071225;color:#f5f9ff;padding:40px"><h1>Security token expired</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p><a style="color:#9bd4ff" href="javascript:history.back()">Go back and refresh</a></p></body>';
        exit;
    }
}

function lqr_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) $path = __DIR__ . '/config.example.php';
    $config = require $path;
    return is_array($config) ? $config : [];
}

require_once __DIR__ . '/storage-sql.php';

function lqr_quests(): array
{
    $quests = require __DIR__ . '/quests.php';
    return is_array($quests) ? $quests : [];
}

function lqr_default_state(): array
{
    return [
        'users' => [],
        'users_by_email' => [],
        'link_states' => [],
        'events' => [],
        'admin_users' => [],
        'admin_password_resets' => [],
        'security_replay' => [],
        'last_response' => null,
        'updated_at' => gmdate('c'),
    ];
}

function lqr_load_state(): array
{
    return lqr_sql_load_state(lqr_config());
}

function lqr_save_state(array $state): void
{
    $state['updated_at'] = gmdate('c');
    lqr_sql_save_state(lqr_config(), $state);
}

function lqr_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function lqr_config_value(array $config, string $key): string
{
    return trim((string)($config[$key] ?? ''));
}

function lqr_is_authenticated(): bool
{
    return !empty($_SESSION['lqr_auth_user_id']);
}

function lqr_current_user_id(array $config): string
{
    if (!empty($_SESSION['lqr_auth_user_id'])) return (string)$_SESSION['lqr_auth_user_id'];
    if (empty($_SESSION['lqr_guest_user_id'])) $_SESSION['lqr_guest_user_id'] = 'guest_' . substr(hash('sha256', session_id() . '|' . microtime(true)), 0, 12);
    return (string)$_SESSION['lqr_guest_user_id'];
}

function lqr_external_user_id(string $userId, string $email = ''): string
{
    $seed = $email !== '' ? strtolower($email) : $userId;
    return 'lqr_' . substr(hash('sha256', $seed), 0, 24);
}

function lqr_default_user(array $config, string $userId): array
{
    return [
        'id' => $userId,
        'display_name' => 'Local Quester',
        'email' => '',
        'password_hash' => '',
        'external_user_id' => lqr_external_user_id($userId),
        'linked_account_id' => '',
        'link_status' => 'not_linked',
        'completed_quests' => [],
        'rewards' => [],
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
}

function lqr_get_user(array $state, array $config, string $userId): array
{
    if (!isset($state['users'][$userId]) || !is_array($state['users'][$userId])) return lqr_default_user($config, $userId);
    return array_replace_recursive(lqr_default_user($config, $userId), $state['users'][$userId]);
}

function lqr_put_user(array &$state, array $user): void
{
    $user['updated_at'] = gmdate('c');
    $state['users'][(string)$user['id']] = $user;
    if (!empty($user['email'])) $state['users_by_email'][strtolower((string)$user['email'])] = (string)$user['id'];
}

function lqr_add_event(array &$state, string $type, string $message, array $context = []): void
{
    array_unshift($state['events'], ['at' => gmdate('c'), 'type' => $type, 'message' => $message, 'context' => $context]);
    $state['events'] = array_slice($state['events'], 0, 100);
}

function lqr_require_real_user(array $user): void
{
    if (empty($user['email']) || !lqr_is_authenticated()) throw new RuntimeException('Create or sign in to a Local Quest account first.');
}

function lqr_action_register(array &$state, array $config): array
{
    $displayName = trim((string)($_POST['display_name'] ?? '')) ?: 'Local Quester';
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
    if (strlen($password) < 8) throw new RuntimeException('Password must be at least 8 characters.');
    if (!empty($state['users_by_email'][$email])) throw new RuntimeException('That email already has a Local Quest account. Sign in instead.');
    $userId = 'user_' . bin2hex(random_bytes(8));
    $user = lqr_default_user($config, $userId);
    $user['display_name'] = $displayName;
    $user['email'] = $email;
    $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $user['external_user_id'] = lqr_external_user_id($userId, $email);
    lqr_put_user($state, $user);
    $_SESSION['lqr_auth_user_id'] = $userId;
    lqr_add_event($state, 'account.registered', 'Local Quest account created.', ['user_id' => $userId, 'external_user_id' => $user['external_user_id']]);
    return $user;
}

function lqr_action_login(array &$state, array $config): array
{
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $userId = (string)($state['users_by_email'][$email] ?? '');
    if ($userId === '' || empty($state['users'][$userId])) throw new RuntimeException('Account not found.');
    $user = lqr_get_user($state, $config, $userId);
    if (!password_verify($password, (string)$user['password_hash'])) throw new RuntimeException('Incorrect password.');
    $_SESSION['lqr_auth_user_id'] = $userId;
    lqr_add_event($state, 'account.login', 'Local Quest account signed in.', ['user_id' => $userId]);
    return $user;
}

function lqr_action_logout(array &$state): void
{
    unset($_SESSION['lqr_auth_user_id']);
    lqr_add_event($state, 'account.logout', 'Local Quest account signed out.');
}

function lqr_action_identify(array &$state, array $config, array &$user): string
{
    lqr_require_real_user($user);
    $user['display_name'] = trim((string)($_POST['display_name'] ?? $user['display_name'])) ?: 'Local Quester';
    lqr_put_user($state, $user);
    lqr_add_event($state, 'user.updated', 'Local Quest profile updated.', ['external_user_id' => $user['external_user_id']]);
    return 'Profile updated.';
}

function lqr_quest_program_id(array $quest, array $config): string
{
    return trim((string)($quest['program_id'] ?: ($config['default_program_id'] ?? '')));
}

function lqr_quest_template_id(array $quest, array $config): string
{
    return trim((string)($quest['template_id'] ?: ($config['default_template_id'] ?? '')));
}

function lqr_call_microgifter(array $config, string $method, string $path, ?array $body = null, array $extraHeaders = []): array
{
    $baseUrl = rtrim(lqr_config_value($config, 'base_url'), '/');
    $apiKey = lqr_config_value($config, 'api_key');
    if ($baseUrl === '' || $apiKey === '' || str_contains($apiKey, 'replace_with')) throw new RuntimeException('Microgifter base_url and api_key must be configured in config.php.');
    $payload = null;
    $headers = array_merge(['Authorization: Bearer ' . $apiKey, 'Accept: application/json'], $extraHeaders);
    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) throw new RuntimeException('Unable to encode request JSON.');
        $headers[] = 'Content-Type: application/json';
    }
    $url = $baseUrl . $path;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Unable to initialize HTTP client.');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
        if ($payload !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $raw = curl_exec($ch);
        if ($raw === false) { $error = curl_error($ch); curl_close($ch); throw new RuntimeException($error); }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr((string)$raw, 0, $headerSize);
        $rawBody = substr((string)$raw, $headerSize);
    } else {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload ?? '', 'ignore_errors' => true, 'timeout' => 30]]);
        $rawBody = file_get_contents($url, false, $context);
        $rawHeaders = implode("\n", $http_response_header ?? []);
        preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
        $status = isset($m[1]) ? (int)$m[1] : 0;
    }
    $decoded = json_decode((string)$rawBody, true);
    return ['status' => $status, 'headers' => $rawHeaders, 'body' => is_array($decoded) ? $decoded : (string)$rawBody];
}

function lqr_action_list_programs(array &$state, array $config): array
{
    $response = lqr_call_microgifter($config, 'GET', '/api/public/v1/programs/index.php');
    $state['last_response'] = $response;
    lqr_add_event($state, 'api.programs.list', 'Listed Microgifter programs.', ['status' => $response['status']]);
    return $response;
}

function lqr_link_return_url(array $config): string
{
    return rtrim(lqr_config_value($config, 'app_public_url'), '/') . '/link-callback.php';
}

function lqr_action_start_account_link(array &$state, array $config, array &$user): array
{
    lqr_require_real_user($user);
    $stateToken = 'lqr_state_' . bin2hex(random_bytes(16));
    $state['link_states'][$stateToken] = ['user_id' => $user['id'], 'external_user_id' => $user['external_user_id'], 'created_at' => gmdate('c')];
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/account-links/start.php', ['external_user_id' => $user['external_user_id'], 'return_url' => lqr_link_return_url($config), 'state' => $stateToken, 'metadata' => ['app' => 'local-quest-rewards', 'local_user_id' => $user['id']]]);
    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.account_link_started', 'Microgifter account link started.', ['status' => $response['status'], 'external_user_id' => $user['external_user_id']]);
    return $response;
}

function lqr_action_sandbox_link(array &$state, array $config, array &$user): array
{
    lqr_require_real_user($user);
    if (empty($config['allow_sandbox_shortcut'])) throw new RuntimeException('Sandbox shortcut is disabled for this app. Use real Microgifter account linking.');
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/sandbox/linked-account.php', ['external_user_id' => $user['external_user_id']]);
    if (is_array($response['body']) && isset($response['body']['linked_account_id'])) {
        $user['linked_account_id'] = (string)$response['body']['linked_account_id'];
        $user['link_status'] = 'sandbox_linked';
        lqr_put_user($state, $user);
    }
    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.sandbox_linked_account', 'Developer sandbox linked account prepared.', ['status' => $response['status'], 'linked_account_id' => $user['linked_account_id']]);
    return $response;
}

function lqr_complete_account_link(array &$state, array $config, array $query): array
{
    $stateToken = trim((string)($query['state'] ?? ''));
    $linkedAccountId = trim((string)($query['linked_account_id'] ?? ''));
    $externalUserId = trim((string)($query['external_user_id'] ?? ''));
    $status = trim((string)($query['status'] ?? ''));
    if ($stateToken === '' || empty($state['link_states'][$stateToken])) throw new RuntimeException('Missing or invalid link state.');
    $linkState = $state['link_states'][$stateToken];
    $userId = (string)$linkState['user_id'];
    $user = lqr_get_user($state, $config, $userId);
    if ($externalUserId !== '' && !hash_equals((string)$user['external_user_id'], $externalUserId)) throw new RuntimeException('Linked external user does not match this account.');
    if ($status !== 'linked' || $linkedAccountId === '') throw new RuntimeException('Microgifter account link was not completed.');
    $user['linked_account_id'] = $linkedAccountId;
    $user['link_status'] = 'linked';
    $user['linked_at'] = gmdate('c');
    lqr_put_user($state, $user);
    unset($state['link_states'][$stateToken]);
    $_SESSION['lqr_auth_user_id'] = $userId;
    lqr_add_event($state, 'microgifter.account_linked', 'Real Microgifter account linked.', ['linked_account_id' => $linkedAccountId, 'external_user_id' => $user['external_user_id']]);
    return $user;
}

function lqr_can_complete_quest(array $user, string $questId, array $quest): array
{
    if (!lqr_is_authenticated() || empty($user['email'])) return [false, 'Sign in before completing quests.'];
    return [true, 'Quest can be completed.'];
}

function lqr_can_issue_reward(array $user, string $questId, array $quest, array $config): array
{
    $permission = is_array($quest['permission'] ?? null) ? $quest['permission'] : [];
    $mode = (string)($config['mode'] ?? 'test');
    $allowedModes = is_array($permission['allowed_modes'] ?? null) ? $permission['allowed_modes'] : ['test', 'live'];
    if (!lqr_is_authenticated() || empty($user['email'])) return [false, 'Sign in before issuing rewards.'];
    if (!in_array($mode, $allowedModes, true)) return [false, 'This quest is not allowed in the current app mode.'];
    if (!empty($permission['requires_completion']) && empty($user['completed_quests'][$questId])) return [false, 'Complete the quest before issuing the reward.'];
    if (!empty($permission['requires_linked_account']) && trim((string)$user['linked_account_id']) === '') return [false, 'Connect a real Microgifter account first.'];
    $max = (int)($permission['max_rewards_per_user'] ?? 1);
    if ($max > 0 && !empty($user['rewards'][$questId])) return [false, 'Reward already issued for this quest/user.'];
    if (lqr_quest_program_id($quest, $config) === '' || lqr_quest_template_id($quest, $config) === '') return [false, 'Program ID and template ID must be configured before issuing rewards.'];
    return [true, 'Reward can be issued.'];
}

function lqr_reward_external_event_id(string $questId, array $user): string
{
    $safeUser = preg_replace('/[^a-zA-Z0-9_.:-]/', '-', (string)$user['external_user_id']);
    return $questId . ':' . $safeUser;
}

function lqr_action_complete_quest(array &$state, array $config, array &$user, string $questId, array $quest): string
{
    [$ok, $message] = lqr_can_complete_quest($user, $questId, $quest);
    if (!$ok) return $message;
    $user['completed_quests'][$questId] = gmdate('c');
    lqr_put_user($state, $user);
    lqr_add_event($state, 'quest.completed', 'Quest completed: ' . (string)$quest['title'], ['quest_id' => $questId, 'user_id' => $user['id']]);
    return 'Quest completed.';
}

function lqr_action_issue_reward(array &$state, array $config, array &$user, string $questId, array $quest): array
{
    [$ok, $message] = lqr_can_issue_reward($user, $questId, $quest, $config);
    if (!$ok) throw new RuntimeException($message);
    $externalEventId = lqr_reward_external_event_id($questId, $user);
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/rewards/issue.php', ['program_id' => lqr_quest_program_id($quest, $config), 'external_event_id' => $externalEventId, 'event_type' => (string)$quest['event_type'], 'recipient' => ['linked_account_id' => $user['linked_account_id']], 'reward' => ['template_id' => lqr_quest_template_id($quest, $config), 'quantity' => 1], 'metadata' => ['demo_app' => 'local-quest-rewards', 'quest_id' => $questId, 'quest_title' => (string)$quest['title'], 'local_user_id' => $user['id']]], ['X-Request-ID: req_' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $externalEventId), 'X-Idempotency-Key: ' . $externalEventId]);
    if (is_array($response['body']) && !empty($response['body']['reward_id'])) {
        $user['rewards'][$questId] = ['reward_id' => (string)$response['body']['reward_id'], 'status' => (string)($response['body']['status'] ?? 'unknown'), 'external_event_id' => $externalEventId, 'issued_at' => gmdate('c'), 'claim_status' => 'available_in_app', 'claim_report_status' => 'not_reported', 'last_checked_at' => null, 'response' => $response['body']];
        lqr_put_user($state, $user);
    }
    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.reward.issue', 'Reward issue requested for ' . (string)$quest['title'], ['status' => $response['status'], 'quest_id' => $questId, 'user_id' => $user['id']]);
    return $response;
}

function lqr_action_check_status(array &$state, array $config, array &$user, string $questId): array
{
    $reward = is_array($user['rewards'][$questId] ?? null) ? $user['rewards'][$questId] : [];
    $rewardId = trim((string)($reward['reward_id'] ?? ''));
    if ($rewardId === '') throw new RuntimeException('No reward ID exists for this quest yet.');
    $response = lqr_call_microgifter($config, 'GET', '/api/public/v1/rewards/status.php?id=' . rawurlencode($rewardId));
    if (is_array($response['body']) && isset($response['body']['reward']) && is_array($response['body']['reward'])) {
        $user['rewards'][$questId]['status'] = (string)($response['body']['reward']['status'] ?? $user['rewards'][$questId]['status']);
        $user['rewards'][$questId]['last_checked_at'] = gmdate('c');
        $user['rewards'][$questId]['status_response'] = $response['body']['reward'];
        lqr_put_user($state, $user);
    }
    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.reward.status', 'Reward status checked.', ['status' => $response['status'], 'quest_id' => $questId, 'reward_id' => $rewardId]);
    return $response;
}

function lqr_reward_item_id(array $reward): string
{
    if (!empty($reward['item_id'])) return (string)$reward['item_id'];
    if (!empty($reward['response']['pppm_item_id'])) return (string)$reward['response']['pppm_item_id'];
    $status = is_array($reward['status_response'] ?? null) ? $reward['status_response'] : [];
    $jobs = is_array($status['jobs'] ?? null) ? $status['jobs'] : [];
    foreach ($jobs as $job) {
        if (!empty($job['pppm_item_id'])) return (string)$job['pppm_item_id'];
    }
    return '';
}

function lqr_wallet_rewards(array $user, array $quests): array
{
    $out = [];
    foreach ($user['rewards'] ?? [] as $questId => $reward) {
        if (!is_array($reward)) continue;
        $quest = is_array($quests[$questId] ?? null) ? $quests[$questId] : [];
        $out[] = ['quest_id' => (string)$questId, 'quest_title' => (string)($quest['title'] ?? $questId), 'reward_label' => (string)($quest['reward_label'] ?? 'Microgift reward'), 'reward_id' => (string)($reward['reward_id'] ?? ''), 'status' => (string)($reward['status'] ?? 'unknown'), 'item_id' => lqr_reward_item_id($reward), 'claim_status' => (string)($reward['claim_status'] ?? 'available_in_app'), 'claim_report_status' => (string)($reward['claim_report_status'] ?? 'not_reported'), 'issued_at' => (string)($reward['issued_at'] ?? '')];
    }
    return $out;
}

function lqr_action_claim_reward(array &$state, array $config, array &$user, string $questId): string
{
    lqr_require_real_user($user);
    if (empty($user['rewards'][$questId]) || !is_array($user['rewards'][$questId])) throw new RuntimeException('Reward not found in this Quest wallet.');
    $reward = $user['rewards'][$questId];
    $reward['claim_status'] = 'claimed_in_quest_app';
    $reward['claimed_at'] = gmdate('c');
    $reward['claim_report_status'] = 'pending_microgifter_claim_api';
    $reward['claim_report_endpoint'] = '/api/public/v1/rewards/claim.php';
    $user['rewards'][$questId] = $reward;
    lqr_put_user($state, $user);
    lqr_add_event($state, 'quest.reward.claimed', 'Reward claimed inside Local Quest. Microgifter claim-report endpoint is required for final platform reporting.', ['quest_id' => $questId, 'reward_id' => (string)($reward['reward_id'] ?? ''), 'item_id' => lqr_reward_item_id($reward)]);
    return 'Reward marked claimed in the Quest app. Platform reporting needs the public claim/report endpoint.';
}

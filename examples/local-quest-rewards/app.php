<?php
declare(strict_types=1);

session_start();

function lqr_config(): array
{
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        $path = __DIR__ . '/config.example.php';
    }
    $config = require $path;
    return is_array($config) ? $config : [];
}

function lqr_quests(): array
{
    $quests = require __DIR__ . '/quests.php';
    return is_array($quests) ? $quests : [];
}

function lqr_data_dir(): string
{
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function lqr_state_path(): string
{
    return lqr_data_dir() . '/state.json';
}

function lqr_default_state(): array
{
    return [
        'users' => [],
        'events' => [],
        'last_response' => null,
        'updated_at' => gmdate('c'),
    ];
}

function lqr_load_state(): array
{
    $path = lqr_state_path();
    if (!is_file($path)) {
        return lqr_default_state();
    }
    $raw = file_get_contents($path);
    $state = json_decode((string)$raw, true);
    return is_array($state) ? array_replace_recursive(lqr_default_state(), $state) : lqr_default_state();
}

function lqr_save_state(array $state): void
{
    $state['updated_at'] = gmdate('c');
    file_put_contents(lqr_state_path(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function lqr_current_user_id(array $config): string
{
    if (empty($_SESSION['lqr_user_id'])) {
        $_SESSION['lqr_user_id'] = 'guest_' . substr(hash('sha256', session_id() . '|' . microtime(true)), 0, 12);
    }
    return (string)$_SESSION['lqr_user_id'];
}

function lqr_default_user(array $config, string $userId): array
{
    return [
        'id' => $userId,
        'display_name' => 'Demo Quester',
        'external_user_id' => (string)($config['demo_external_user_id'] ?? 'quester-9001'),
        'linked_account_id' => '',
        'completed_quests' => [],
        'rewards' => [],
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
}

function lqr_get_user(array $state, array $config, string $userId): array
{
    if (!isset($state['users'][$userId]) || !is_array($state['users'][$userId])) {
        return lqr_default_user($config, $userId);
    }
    return array_replace_recursive(lqr_default_user($config, $userId), $state['users'][$userId]);
}

function lqr_put_user(array &$state, array $user): void
{
    $user['updated_at'] = gmdate('c');
    $state['users'][(string)$user['id']] = $user;
}

function lqr_add_event(array &$state, string $type, string $message, array $context = []): void
{
    array_unshift($state['events'], [
        'at' => gmdate('c'),
        'type' => $type,
        'message' => $message,
        'context' => $context,
    ]);
    $state['events'] = array_slice($state['events'], 0, 80);
}

function lqr_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function lqr_config_value(array $config, string $key): string
{
    return trim((string)($config[$key] ?? ''));
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
    if ($baseUrl === '' || $apiKey === '' || str_contains($apiKey, 'replace_with')) {
        throw new RuntimeException('Microgifter base_url and api_key must be configured in config.php.');
    }

    $payload = null;
    $headers = array_merge([
        'Authorization: Bearer ' . $apiKey,
        'Accept: application/json',
    ], $extraHeaders);

    if ($body !== null) {
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payload)) {
            throw new RuntimeException('Unable to encode request JSON.');
        }
        $headers[] = 'Content-Type: application/json';
    }

    $url = $baseUrl . $path;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Unable to initialize HTTP client.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException($error);
        }
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $rawHeaders = substr((string)$raw, 0, $headerSize);
        $rawBody = substr((string)$raw, $headerSize);
    } else {
        $headerText = implode("\r\n", $headers);
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headerText,
            'content' => $payload ?? '',
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $rawBody = file_get_contents($url, false, $context);
        $rawHeaders = implode("\n", $http_response_header ?? []);
        preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $m);
        $status = isset($m[1]) ? (int)$m[1] : 0;
    }

    $decoded = json_decode((string)$rawBody, true);
    return [
        'status' => $status,
        'headers' => $rawHeaders,
        'body' => is_array($decoded) ? $decoded : (string)$rawBody,
    ];
}

function lqr_can_complete_quest(array $user, string $questId, array $quest): array
{
    if (!isset($quest['permission']) || !is_array($quest['permission'])) {
        return [true, 'Quest can be completed.'];
    }
    return [true, 'Quest can be completed.'];
}

function lqr_can_issue_reward(array $user, string $questId, array $quest, array $config): array
{
    $permission = is_array($quest['permission'] ?? null) ? $quest['permission'] : [];
    $mode = (string)($config['mode'] ?? 'sandbox');
    $allowedModes = is_array($permission['allowed_modes'] ?? null) ? $permission['allowed_modes'] : ['sandbox'];
    if (!in_array($mode, $allowedModes, true)) {
        return [false, 'This quest is not allowed in the current app mode.'];
    }
    if (!empty($permission['requires_completion']) && empty($user['completed_quests'][$questId])) {
        return [false, 'Complete the quest before issuing the reward.'];
    }
    if (!empty($permission['requires_linked_account']) && trim((string)$user['linked_account_id']) === '') {
        return [false, 'Create or connect a linked Microgifter account first.'];
    }
    $max = (int)($permission['max_rewards_per_user'] ?? 1);
    if ($max > 0 && !empty($user['rewards'][$questId])) {
        return [false, 'Reward already issued for this quest/user.'];
    }
    if (lqr_quest_program_id($quest, $config) === '' || lqr_quest_template_id($quest, $config) === '') {
        return [false, 'Program ID and template ID must be configured before issuing rewards.'];
    }
    return [true, 'Reward can be issued.'];
}

function lqr_reward_external_event_id(string $questId, array $user): string
{
    $safeUser = preg_replace('/[^a-zA-Z0-9_.:-]/', '-', (string)$user['external_user_id']);
    return $questId . ':' . $safeUser;
}

function lqr_action_identify(array &$state, array $config, array &$user): string
{
    $user['display_name'] = trim((string)($_POST['display_name'] ?? $user['display_name'])) ?: 'Demo Quester';
    $user['external_user_id'] = trim((string)($_POST['external_user_id'] ?? $user['external_user_id'])) ?: (string)($config['demo_external_user_id'] ?? 'quester-9001');
    lqr_put_user($state, $user);
    lqr_add_event($state, 'user.updated', 'Demo user profile updated.', ['external_user_id' => $user['external_user_id']]);
    return 'Demo user updated.';
}

function lqr_action_list_programs(array &$state, array $config): array
{
    $response = lqr_call_microgifter($config, 'GET', '/api/public/v1/programs/index.php');
    $state['last_response'] = $response;
    lqr_add_event($state, 'api.programs.list', 'Listed Microgifter programs.', ['status' => $response['status']]);
    return $response;
}

function lqr_action_sandbox_link(array &$state, array $config, array &$user): array
{
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/sandbox/linked-account.php', [
        'external_user_id' => $user['external_user_id'],
    ]);
    if (is_array($response['body']) && isset($response['body']['linked_account_id'])) {
        $user['linked_account_id'] = (string)$response['body']['linked_account_id'];
        lqr_put_user($state, $user);
    }
    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.linked_account', 'Sandbox linked account prepared.', ['status' => $response['status'], 'linked_account_id' => $user['linked_account_id']]);
    return $response;
}

function lqr_action_complete_quest(array &$state, array $config, array &$user, string $questId, array $quest): string
{
    [$ok, $message] = lqr_can_complete_quest($user, $questId, $quest);
    if (!$ok) {
        return $message;
    }
    $user['completed_quests'][$questId] = gmdate('c');
    lqr_put_user($state, $user);
    lqr_add_event($state, 'quest.completed', 'Quest completed: ' . (string)$quest['title'], ['quest_id' => $questId]);
    return 'Quest completed.';
}

function lqr_action_issue_reward(array &$state, array $config, array &$user, string $questId, array $quest): array
{
    [$ok, $message] = lqr_can_issue_reward($user, $questId, $quest, $config);
    if (!$ok) {
        throw new RuntimeException($message);
    }
    $externalEventId = lqr_reward_external_event_id($questId, $user);
    $response = lqr_call_microgifter($config, 'POST', '/api/public/v1/rewards/issue.php', [
        'program_id' => lqr_quest_program_id($quest, $config),
        'external_event_id' => $externalEventId,
        'event_type' => (string)$quest['event_type'],
        'recipient' => ['linked_account_id' => $user['linked_account_id']],
        'reward' => ['template_id' => lqr_quest_template_id($quest, $config), 'quantity' => 1],
        'metadata' => [
            'demo_app' => 'local-quest-rewards',
            'quest_id' => $questId,
            'quest_title' => (string)$quest['title'],
        ],
    ], [
        'X-Request-ID: req_' . preg_replace('/[^a-zA-Z0-9_.:-]/', '_', $externalEventId),
        'X-Idempotency-Key: ' . $externalEventId,
    ]);

    if (is_array($response['body']) && !empty($response['body']['reward_id'])) {
        $user['rewards'][$questId] = [
            'reward_id' => (string)$response['body']['reward_id'],
            'status' => (string)($response['body']['status'] ?? 'unknown'),
            'external_event_id' => $externalEventId,
            'issued_at' => gmdate('c'),
            'last_checked_at' => null,
            'response' => $response['body'],
        ];
        lqr_put_user($state, $user);
    }

    $state['last_response'] = $response;
    lqr_add_event($state, 'microgifter.reward.issue', 'Reward issue requested for ' . (string)$quest['title'], ['status' => $response['status'], 'quest_id' => $questId]);
    return $response;
}

function lqr_action_check_status(array &$state, array $config, array &$user, string $questId): array
{
    $reward = is_array($user['rewards'][$questId] ?? null) ? $user['rewards'][$questId] : [];
    $rewardId = trim((string)($reward['reward_id'] ?? ''));
    if ($rewardId === '') {
        throw new RuntimeException('No reward ID exists for this quest yet.');
    }
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

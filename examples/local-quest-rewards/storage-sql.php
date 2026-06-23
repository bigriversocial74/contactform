<?php
declare(strict_types=1);

function lqr_storage_driver(array $config): string
{
    $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
    return strtolower(trim((string)($storage['driver'] ?? 'json')));
}

function lqr_storage_uses_sql(array $config): bool
{
    return in_array(lqr_storage_driver($config), ['mysql', 'mariadb', 'pdo_mysql'], true);
}

function lqr_sql_json($value): ?string
{
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

function lqr_sql_decode($value, $fallback = [])
{
    if ($value === null || $value === '') return $fallback;
    $decoded = json_decode((string)$value, true);
    return $decoded === null && json_last_error() !== JSON_ERROR_NONE ? $fallback : $decoded;
}

function lqr_sql_db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
    $dsn = (string)($storage['dsn'] ?? '');
    $username = (string)($storage['username'] ?? '');
    $password = (string)($storage['password'] ?? '');
    $options = is_array($storage['options'] ?? null) ? $storage['options'] : [];
    if ($dsn === '') throw new RuntimeException('SQL storage DSN is missing.');
    $pdo = new PDO($dsn, $username, $password, $options + [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function lqr_sql_datetime(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') return null;
    $time = strtotime($value);
    return $time === false ? null : gmdate('Y-m-d H:i:s', $time);
}

function lqr_sql_iso(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') return '';
    $time = strtotime($value);
    return $time === false ? $value : gmdate('c', $time);
}

function lqr_sql_load_state(array $config): array
{
    $pdo = lqr_sql_db($config);
    $state = lqr_default_state();
    $state['users'] = [];
    $state['users_by_email'] = [];
    $state['link_states'] = [];
    $state['admin_users'] = [];
    $state['admin_password_resets'] = [];

    $stmt = $pdo->query('SELECT * FROM lqr_users ORDER BY created_at ASC,id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $userId = (string)$row['public_id'];
        $user = [
            'id' => $userId,
            'display_name' => (string)$row['display_name'],
            'email' => (string)$row['email'],
            'password_hash' => (string)$row['password_hash'],
            'external_user_id' => (string)$row['external_user_id'],
            'linked_account_id' => (string)($row['linked_account_id'] ?? ''),
            'link_status' => (string)$row['link_status'],
            'linked_at' => lqr_sql_iso($row['linked_at'] ?? null),
            'completed_quests' => [],
            'rewards' => [],
            'created_at' => lqr_sql_iso($row['created_at'] ?? null),
            'updated_at' => lqr_sql_iso($row['updated_at'] ?? null),
        ];
        $state['users'][$userId] = $user;
        if ($user['email'] !== '') $state['users_by_email'][strtolower($user['email'])] = $userId;
    }

    $stmt = $pdo->query('SELECT * FROM lqr_quest_completions ORDER BY completed_at ASC,id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $userId = (string)$row['user_public_id'];
        if (!isset($state['users'][$userId])) continue;
        $state['users'][$userId]['completed_quests'][(string)$row['quest_key']] = lqr_sql_iso($row['completed_at'] ?? null);
    }

    $stmt = $pdo->query('SELECT * FROM lqr_rewards ORDER BY created_at ASC,id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $userId = (string)$row['user_public_id'];
        $questId = (string)$row['quest_key'];
        if (!isset($state['users'][$userId])) continue;
        $state['users'][$userId]['rewards'][$questId] = [
            'reward_id' => (string)$row['reward_id'],
            'external_event_id' => (string)$row['external_event_id'],
            'status' => (string)$row['status'],
            'item_id' => (string)($row['item_id'] ?? ''),
            'item_status' => (string)($row['item_status'] ?? ''),
            'claim_status' => (string)$row['claim_status'],
            'claim_report_status' => (string)$row['claim_report_status'],
            'microgifter_event_id' => (string)($row['microgifter_event_id'] ?? ''),
            'response' => lqr_sql_decode($row['response_json'] ?? null, []),
            'status_response' => lqr_sql_decode($row['status_response_json'] ?? null, []),
            'claim_report_response' => lqr_sql_decode($row['claim_report_response_json'] ?? null, []),
            'issued_at' => lqr_sql_iso($row['issued_at'] ?? null),
            'last_checked_at' => lqr_sql_iso($row['last_checked_at'] ?? null),
            'claimed_at' => lqr_sql_iso($row['claimed_at'] ?? null),
        ];
    }

    $stmt = $pdo->query("SELECT * FROM lqr_link_states WHERE status='pending' ORDER BY created_at DESC,id DESC");
    foreach ($stmt->fetchAll() as $row) {
        $state['link_states'][(string)$row['state_token']] = [
            'user_id' => (string)$row['user_public_id'],
            'external_user_id' => (string)$row['external_user_id'],
            'created_at' => lqr_sql_iso($row['created_at'] ?? null),
        ];
    }

    $stmt = $pdo->query('SELECT * FROM lqr_admin_users ORDER BY created_at ASC,id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $adminId = (string)$row['public_id'];
        $state['admin_users'][$adminId] = [
            'id' => $adminId,
            'username' => (string)$row['username'],
            'email' => (string)($row['email'] ?? ''),
            'display_name' => (string)($row['display_name'] ?? ''),
            'password_hash' => (string)$row['password_hash'],
            'role_key' => (string)$row['role_key'],
            'status' => (string)$row['status'],
            'created_at' => lqr_sql_iso($row['created_at'] ?? null),
            'updated_at' => lqr_sql_iso($row['updated_at'] ?? null),
            'last_login_at' => lqr_sql_iso($row['last_login_at'] ?? null),
        ];
    }

    try {
        $stmt = $pdo->query('SELECT * FROM lqr_admin_password_resets ORDER BY created_at DESC,id DESC LIMIT 100');
        foreach ($stmt->fetchAll() as $row) {
            $state['admin_password_resets'][] = [
                'admin_id' => (string)$row['admin_public_id'],
                'token_hash' => (string)$row['token_hash'],
                'created_at' => lqr_sql_iso($row['created_at'] ?? null),
                'expires_at' => lqr_sql_iso($row['expires_at'] ?? null),
                'used_at' => lqr_sql_iso($row['used_at'] ?? null),
            ];
        }
    } catch (Throwable $ignored) {}

    $stmt = $pdo->query('SELECT * FROM lqr_events ORDER BY created_at DESC,id DESC LIMIT 100');
    foreach ($stmt->fetchAll() as $row) {
        $state['events'][] = [
            'at' => lqr_sql_iso($row['created_at'] ?? null),
            'type' => (string)$row['event_type'],
            'message' => (string)$row['message'],
            'context' => lqr_sql_decode($row['context_json'] ?? null, []),
        ];
    }

    $stmt = $pdo->prepare("SELECT state_json FROM lqr_app_state WHERE state_key=? LIMIT 1");
    foreach (['last_response','last_scan'] as $key) {
        $stmt->execute([$key]);
        $raw = $stmt->fetchColumn();
        if ($raw !== false) $state[$key] = lqr_sql_decode($raw, null);
    }
    $state['updated_at'] = gmdate('c');
    return $state;
}

function lqr_sql_save_state(array $config, array $state): void
{
    $pdo = lqr_sql_db($config);
    $pdo->beginTransaction();
    try {
        $userStmt = $pdo->prepare("INSERT INTO lqr_users (public_id,display_name,email,password_hash,external_user_id,linked_account_id,link_status,linked_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),password_hash=VALUES(password_hash),external_user_id=VALUES(external_user_id),linked_account_id=VALUES(linked_account_id),link_status=VALUES(link_status),linked_at=VALUES(linked_at),updated_at=NOW()");
        $clearCompletions = $pdo->prepare('DELETE FROM lqr_quest_completions WHERE user_public_id=?');
        $completionStmt = $pdo->prepare('INSERT INTO lqr_quest_completions (user_public_id,quest_key,completed_at,metadata_json) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at),metadata_json=VALUES(metadata_json)');
        $clearRewards = $pdo->prepare('DELETE FROM lqr_rewards WHERE user_public_id=?');
        $rewardStmt = $pdo->prepare("INSERT INTO lqr_rewards (user_public_id,quest_key,reward_id,external_event_id,status,item_id,item_status,claim_status,claim_report_status,microgifter_event_id,response_json,status_response_json,claim_report_response_json,issued_at,last_checked_at,claimed_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),item_id=VALUES(item_id),item_status=VALUES(item_status),claim_status=VALUES(claim_status),claim_report_status=VALUES(claim_report_status),microgifter_event_id=VALUES(microgifter_event_id),response_json=VALUES(response_json),status_response_json=VALUES(status_response_json),claim_report_response_json=VALUES(claim_report_response_json),last_checked_at=VALUES(last_checked_at),claimed_at=VALUES(claimed_at),updated_at=NOW()");
        foreach ((array)($state['users'] ?? []) as $userId => $user) {
            if (!is_array($user)) continue;
            $publicId = (string)($user['id'] ?? $userId);
            $userStmt->execute([$publicId,(string)($user['display_name'] ?? 'Local Quester'),(string)($user['email'] ?? ''),(string)($user['password_hash'] ?? ''),(string)($user['external_user_id'] ?? ''),(string)($user['linked_account_id'] ?? '') ?: null,(string)($user['link_status'] ?? 'not_linked'),lqr_sql_datetime((string)($user['linked_at'] ?? ''))]);
            $clearCompletions->execute([$publicId]);
            foreach ((array)($user['completed_quests'] ?? []) as $questKey => $completedAt) {
                $completionStmt->execute([$publicId,(string)$questKey,lqr_sql_datetime((string)$completedAt) ?: gmdate('Y-m-d H:i:s'),lqr_sql_json([])]);
            }
            $clearRewards->execute([$publicId]);
            foreach ((array)($user['rewards'] ?? []) as $questKey => $reward) {
                if (!is_array($reward) || empty($reward['reward_id'])) continue;
                $rewardStmt->execute([$publicId,(string)$questKey,(string)$reward['reward_id'],(string)($reward['external_event_id'] ?? ''),(string)($reward['status'] ?? 'unknown'),(string)($reward['item_id'] ?? '') ?: null,(string)($reward['item_status'] ?? '') ?: null,(string)($reward['claim_status'] ?? 'available_in_app'),(string)($reward['claim_report_status'] ?? 'not_reported'),(string)($reward['microgifter_event_id'] ?? '') ?: null,lqr_sql_json($reward['response'] ?? []),lqr_sql_json($reward['status_response'] ?? []),lqr_sql_json($reward['claim_report_response'] ?? []),lqr_sql_datetime((string)($reward['issued_at'] ?? '')) ?: gmdate('Y-m-d H:i:s'),lqr_sql_datetime((string)($reward['last_checked_at'] ?? '')),lqr_sql_datetime((string)($reward['claimed_at'] ?? ''))]);
            }
        }

        $pdo->exec('DELETE FROM lqr_link_states');
        $linkStmt = $pdo->prepare("INSERT INTO lqr_link_states (state_token,user_public_id,external_user_id,status,created_at) VALUES (?,?,?,?,?)");
        foreach ((array)($state['link_states'] ?? []) as $token => $link) {
            if (!is_array($link)) continue;
            $linkStmt->execute([(string)$token,(string)($link['user_id'] ?? ''),(string)($link['external_user_id'] ?? ''),'pending',lqr_sql_datetime((string)($link['created_at'] ?? '')) ?: gmdate('Y-m-d H:i:s')]);
        }

        if (isset($state['admin_users']) && is_array($state['admin_users'])) {
            $adminStmt = $pdo->prepare("INSERT INTO lqr_admin_users (public_id,username,email,password_hash,display_name,role_key,status,last_login_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE username=VALUES(username),email=VALUES(email),password_hash=VALUES(password_hash),display_name=VALUES(display_name),role_key=VALUES(role_key),status=VALUES(status),last_login_at=VALUES(last_login_at),updated_at=NOW()");
            foreach ($state['admin_users'] as $adminId => $admin) {
                if (!is_array($admin)) continue;
                $adminStmt->execute([(string)($admin['id'] ?? $adminId),(string)$admin['username'],(string)($admin['email'] ?? ''),(string)$admin['password_hash'],(string)($admin['display_name'] ?? $admin['username']),(string)($admin['role_key'] ?? 'admin'),(string)($admin['status'] ?? 'active'),lqr_sql_datetime((string)($admin['last_login_at'] ?? ''))]);
            }
        }

        try {
            $resetStmt = $pdo->prepare("INSERT INTO lqr_admin_password_resets (admin_public_id,token_hash,created_at,expires_at,used_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE used_at=VALUES(used_at)");
            foreach ((array)($state['admin_password_resets'] ?? []) as $record) {
                if (!is_array($record) || empty($record['token_hash'])) continue;
                $resetStmt->execute([(string)$record['admin_id'],(string)$record['token_hash'],lqr_sql_datetime((string)($record['created_at'] ?? '')) ?: gmdate('Y-m-d H:i:s'),lqr_sql_datetime((string)($record['expires_at'] ?? '')) ?: gmdate('Y-m-d H:i:s'),lqr_sql_datetime((string)($record['used_at'] ?? ''))]);
            }
        } catch (Throwable $ignored) {}

        $pdo->exec('DELETE FROM lqr_events');
        $eventStmt = $pdo->prepare('INSERT INTO lqr_events (event_type,message,context_json,created_at) VALUES (?,?,?,?)');
        foreach (array_reverse(array_slice((array)($state['events'] ?? []), 0, 100)) as $event) {
            if (!is_array($event)) continue;
            $eventStmt->execute([(string)($event['type'] ?? 'event'),(string)($event['message'] ?? ''),lqr_sql_json($event['context'] ?? []),lqr_sql_datetime((string)($event['at'] ?? '')) ?: gmdate('Y-m-d H:i:s')]);
        }

        $appStateStmt = $pdo->prepare('INSERT INTO lqr_app_state (state_key,state_json,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE state_json=VALUES(state_json),updated_at=NOW()');
        foreach (['last_response','last_scan'] as $key) {
            if (array_key_exists($key, $state)) $appStateStmt->execute([$key, lqr_sql_json($state[$key])]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

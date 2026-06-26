<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-actions.php';

if (!function_exists('mg_share_market_approval_pending_statuses')) {
    function mg_share_market_approval_pending_statuses(): array
    {
        return ['awaiting_first_approval', 'awaiting_second_approval'];
    }
}

if (!function_exists('mg_share_market_approval_decision_phrases')) {
    function mg_share_market_approval_decision_phrases(): array
    {
        return [
            'approve' => 'APPROVE REQUEST',
            'reject' => 'REJECT REQUEST',
            'cancel' => 'CANCEL REQUEST',
            'escalate' => 'ESCALATE REQUEST',
            'request_freeze' => 'REQUEST TARGET FREEZE',
            'record_expiry' => 'RECORD EXPIRY',
        ];
    }
}

if (!function_exists('mg_share_market_approval_balance_actions')) {
    function mg_share_market_approval_balance_actions(): array
    {
        return [
            'create_master_pool' => ['direction' => 1, 'label' => 'Master platform supply', 'requires_current' => false],
            'mint_platform_shares' => ['direction' => 1, 'label' => 'Master platform supply', 'requires_current' => true],
            'burn_platform_shares' => ['direction' => -1, 'label' => 'Master platform supply', 'requires_current' => true],
            'allocate_merchant_credits' => ['direction' => 1, 'label' => 'Merchant treasury credits', 'requires_current' => true],
        ];
    }
}

if (!function_exists('mg_share_market_approval_canonical_hash')) {
    function mg_share_market_approval_canonical_hash(array $payload): string
    {
        unset($payload['payload_hash']);
        $canonical = mg_share_market_admin_canonicalize($payload);
        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}

if (!function_exists('mg_share_market_approval_validation_key')) {
    function mg_share_market_approval_validation_key(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('A secure session is required.');
        }
        if (!isset($_SESSION['mg_share_market_validation_key']) || !is_string($_SESSION['mg_share_market_validation_key']) || strlen($_SESSION['mg_share_market_validation_key']) !== 64) {
            $_SESSION['mg_share_market_validation_key'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['mg_share_market_validation_key'];
    }
}

if (!function_exists('mg_share_market_approval_issue_validation_token')) {
    function mg_share_market_approval_issue_validation_token(array $manifest): string
    {
        $material = implode('|', [
            (string)($manifest['manifest_id'] ?? ''),
            (string)($manifest['payload_hash'] ?? ''),
            (string)($manifest['actor_user_id'] ?? ''),
        ]);
        return hash_hmac('sha256', $material, mg_share_market_approval_validation_key());
    }
}

if (!function_exists('mg_share_market_approval_verify_validation_token')) {
    function mg_share_market_approval_verify_validation_token(array $manifest, string $token): bool
    {
        if ($token === '' || strlen($token) !== 64) return false;
        return hash_equals(mg_share_market_approval_issue_validation_token($manifest), $token);
    }
}

if (!function_exists('mg_share_market_approval_verify_manifest')) {
    function mg_share_market_approval_verify_manifest(array $manifest, array $user): array
    {
        if (!mg_share_market_admin_authorized($user)) {
            throw new DomainException('Share Market Admin permission is required.');
        }
        if (($manifest['mode'] ?? null) !== 'dry_run' || ($manifest['mutation_enabled'] ?? true) !== false) {
            throw new InvalidArgumentException('Only locked dry-run manifests can enter the approval queue.');
        }
        if (($manifest['execution_status'] ?? null) !== 'validated_not_executed') {
            throw new InvalidArgumentException('The manifest execution state is invalid.');
        }
        if ((int)($manifest['actor_user_id'] ?? 0) !== (int)($user['id'] ?? 0)) {
            throw new DomainException('The manifest belongs to a different administrator session.');
        }

        $action = (string)($manifest['action'] ?? '');
        $definitions = mg_share_market_admin_action_definitions();
        if ($action === '' || !isset($definitions[$action])) {
            throw new InvalidArgumentException('The manifest action is not recognized.');
        }
        $definition = $definitions[$action];
        if ((string)($manifest['event_type'] ?? '') !== (string)$definition['event_type']) {
            throw new InvalidArgumentException('The manifest event type does not match the action contract.');
        }
        if ((string)($manifest['target_type'] ?? '') !== (string)$definition['target_type']) {
            throw new InvalidArgumentException('The manifest target type does not match the action contract.');
        }
        if ((int)($manifest['required_approvals'] ?? 0) !== (int)$definition['required_approvals']) {
            throw new InvalidArgumentException('The manifest approval requirement does not match the action contract.');
        }
        if ((bool)($manifest['super_admin_required'] ?? false) !== (bool)$definition['super_admin_required']) {
            throw new InvalidArgumentException('The manifest privilege requirement does not match the action contract.');
        }

        $providedHash = (string)($manifest['payload_hash'] ?? '');
        $hashPayload = $manifest;
        unset($hashPayload['manifest_id'], $hashPayload['created_at'], $hashPayload['payload_hash'], $hashPayload['execution_status']);
        $expectedHash = hash('sha256', json_encode(mg_share_market_admin_canonicalize($hashPayload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($providedHash === '' || !hash_equals($expectedHash, $providedHash)) {
            throw new InvalidArgumentException('The manifest payload hash is invalid.');
        }
        return $definition;
    }
}

if (!function_exists('mg_share_market_approval_projection')) {
    function mg_share_market_approval_projection(array $manifest, mixed $currentBalanceInput): array
    {
        $action = (string)($manifest['action'] ?? '');
        $rules = mg_share_market_approval_balance_actions();
        if (!isset($rules[$action])) {
            return [
                'type' => 'state_only',
                'label' => 'No balance change',
                'current_balance' => null,
                'delta' => 0,
                'projected_balance' => null,
            ];
        }

        $rule = $rules[$action];
        $currentBalance = filter_var($currentBalanceInput, FILTER_VALIDATE_INT);
        if ($currentBalance === false || $currentBalance < 0 || $currentBalance > 1000000000000) {
            if (!empty($rule['requires_current'])) {
                throw new InvalidArgumentException('Enter a current balance between 0 and 1,000,000,000,000.');
            }
            $currentBalance = 0;
        }
        $amount = (int)($manifest['amount'] ?? 0);
        if ($amount < 1) throw new InvalidArgumentException('This action requires a positive share or credit amount.');
        $delta = ((int)$rule['direction']) * $amount;
        $projected = (int)$currentBalance + $delta;
        if ($projected < 0) {
            throw new InvalidArgumentException('The projected balance cannot be negative.');
        }

        return [
            'type' => 'balance',
            'label' => (string)$rule['label'],
            'current_balance' => (int)$currentBalance,
            'delta' => $delta,
            'projected_balance' => $projected,
        ];
    }
}

if (!function_exists('mg_share_market_approval_verify_password')) {
    function mg_share_market_approval_verify_password(PDO $pdo, int $userId, string $password): void
    {
        if ($userId < 1 || $password === '') throw new DomainException('Fresh password verification is required.');
        $stmt = $pdo->prepare('SELECT password_hash,status FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)($row['status'] ?? '') !== 'active' || !password_verify($password, (string)($row['password_hash'] ?? ''))) {
            throw new DomainException('Fresh password verification failed.');
        }
    }
}

if (!function_exists('mg_share_market_approval_append_event')) {
    function mg_share_market_approval_append_event(PDO $pdo, string $eventType, array $payload, int $userId): int
    {
        $payload['payload_hash'] = mg_share_market_approval_canonical_hash($payload);
        $stmt = $pdo->prepare('INSERT INTO events (event_type,user_id,payload_json,created_at) VALUES (?,?,?,NOW())');
        $stmt->execute([
            $eventType,
            $userId,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('mg_share_market_approval_fetch_events')) {
    function mg_share_market_approval_fetch_events(PDO $pdo, int $limit = 1000): array
    {
        $limit = max(1, min(2000, $limit));
        $stmt = $pdo->query("SELECT id,event_type,user_id,payload_json,created_at FROM events WHERE event_type LIKE 'share_market.approval.%' ORDER BY id DESC LIMIT {$limit}");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $events = [];
        foreach (array_reverse($rows ?: []) as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) $payload = [];
            $events[] = [
                'id' => (int)$row['id'],
                'event_type' => (string)$row['event_type'],
                'user_id' => (int)($row['user_id'] ?? 0),
                'payload' => $payload,
                'created_at' => (string)$row['created_at'],
            ];
        }
        return $events;
    }
}

if (!function_exists('mg_share_market_approval_fold_events')) {
    function mg_share_market_approval_fold_events(array $events, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $items = [];
        foreach ($events as $event) {
            $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
            $requestId = (string)($payload['request_id'] ?? '');
            if ($requestId === '') continue;
            $eventType = (string)($event['event_type'] ?? '');
            $actorId = (int)($event['user_id'] ?? $payload['actor_user_id'] ?? 0);
            $createdAt = (string)($event['created_at'] ?? $payload['created_at'] ?? '');

            if ($eventType === 'share_market.approval.requested') {
                if (isset($items[$requestId])) continue;
                $manifest = is_array($payload['manifest'] ?? null) ? $payload['manifest'] : [];
                $items[$requestId] = [
                    'request_id' => $requestId,
                    'status' => 'awaiting_first_approval',
                    'manifest' => $manifest,
                    'projection' => is_array($payload['projection'] ?? null) ? $payload['projection'] : [],
                    'requester_user_id' => (int)($payload['requester_user_id'] ?? $actorId),
                    'required_approvals' => max(1, (int)($payload['required_approvals'] ?? $manifest['required_approvals'] ?? 1)),
                    'approvals' => [],
                    'rejection' => null,
                    'cancellation' => null,
                    'escalated' => false,
                    'freeze_requested' => false,
                    'created_at' => $createdAt,
                    'expires_at' => (string)($payload['expires_at'] ?? ''),
                    'last_event_hash' => (string)($payload['payload_hash'] ?? ''),
                    'last_event_id' => (int)($event['id'] ?? 0),
                    'timeline' => [[
                        'event_type' => $eventType,
                        'actor_user_id' => $actorId,
                        'created_at' => $createdAt,
                        'note' => (string)($payload['note'] ?? 'Approval request created.'),
                        'payload_hash' => (string)($payload['payload_hash'] ?? ''),
                    ]],
                ];
                continue;
            }

            if (!isset($items[$requestId])) continue;
            $item = &$items[$requestId];
            $item['last_event_hash'] = (string)($payload['payload_hash'] ?? $item['last_event_hash']);
            $item['last_event_id'] = (int)($event['id'] ?? $item['last_event_id']);
            $item['timeline'][] = [
                'event_type' => $eventType,
                'actor_user_id' => $actorId,
                'created_at' => $createdAt,
                'note' => (string)($payload['note'] ?? ''),
                'payload_hash' => (string)($payload['payload_hash'] ?? ''),
            ];

            if ($eventType === 'share_market.approval.approved') {
                $item['approvals'][(string)$actorId] = [
                    'actor_user_id' => $actorId,
                    'created_at' => $createdAt,
                    'note' => (string)($payload['note'] ?? ''),
                ];
                $count = count($item['approvals']);
                $item['status'] = $count >= (int)$item['required_approvals'] ? 'approved' : 'awaiting_second_approval';
            } elseif ($eventType === 'share_market.approval.rejected') {
                $item['status'] = 'rejected';
                $item['rejection'] = ['actor_user_id' => $actorId, 'note' => (string)($payload['note'] ?? ''), 'created_at' => $createdAt];
            } elseif ($eventType === 'share_market.approval.cancelled') {
                $item['status'] = 'cancelled';
                $item['cancellation'] = ['actor_user_id' => $actorId, 'note' => (string)($payload['note'] ?? ''), 'created_at' => $createdAt];
            } elseif ($eventType === 'share_market.approval.escalated') {
                $item['escalated'] = true;
            } elseif ($eventType === 'share_market.approval.freeze_requested') {
                $item['freeze_requested'] = true;
            } elseif ($eventType === 'share_market.approval.expired') {
                $item['status'] = 'expired';
            }
            unset($item);
        }

        foreach ($items as &$item) {
            $expiresAt = (string)($item['expires_at'] ?? '');
            if (in_array($item['status'], mg_share_market_approval_pending_statuses(), true) && $expiresAt !== '') {
                try {
                    if (new DateTimeImmutable($expiresAt) <= $now) $item['status'] = 'expired';
                } catch (Throwable) {
                    $item['status'] = 'expired';
                }
            }
            $item['approvals'] = array_values($item['approvals']);
            $item['approval_count'] = count($item['approvals']);
        }
        unset($item);

        uasort($items, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return array_values($items);
    }
}

if (!function_exists('mg_share_market_approval_enrich_users')) {
    function mg_share_market_approval_enrich_users(PDO $pdo, array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int)($item['requester_user_id'] ?? 0);
            foreach (($item['approvals'] ?? []) as $approval) $ids[] = (int)($approval['actor_user_id'] ?? 0);
            foreach (($item['timeline'] ?? []) as $timeline) $ids[] = (int)($timeline['actor_user_id'] ?? 0);
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        $users = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id,email,COALESCE(NULLIF(display_name,''),NULLIF(full_name,''),email) AS name FROM users WHERE id IN ({$placeholders})");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $users[(int)$row['id']] = ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'email' => (string)$row['email']];
            }
        }
        foreach ($items as &$item) {
            $requesterId = (int)($item['requester_user_id'] ?? 0);
            $item['requester'] = $users[$requesterId] ?? ['id' => $requesterId, 'name' => 'User ' . $requesterId, 'email' => ''];
            foreach ($item['approvals'] as &$approval) {
                $id = (int)($approval['actor_user_id'] ?? 0);
                $approval['actor'] = $users[$id] ?? ['id' => $id, 'name' => 'User ' . $id, 'email' => ''];
            }
            unset($approval);
            foreach ($item['timeline'] as &$timeline) {
                $id = (int)($timeline['actor_user_id'] ?? 0);
                $timeline['actor'] = $users[$id] ?? ['id' => $id, 'name' => 'User ' . $id, 'email' => ''];
            }
            unset($timeline);
        }
        unset($item);
        return $items;
    }
}

if (!function_exists('mg_share_market_approval_queue')) {
    function mg_share_market_approval_queue(PDO $pdo, array $viewer): array
    {
        $items = mg_share_market_approval_enrich_users($pdo, mg_share_market_approval_fold_events(mg_share_market_approval_fetch_events($pdo)));
        $viewerId = (int)($viewer['id'] ?? 0);
        $isSuper = mg_share_market_admin_is_super_admin($viewer);
        $summary = ['total' => count($items), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'expired' => 0, 'escalated' => 0];
        foreach ($items as &$item) {
            $status = (string)$item['status'];
            if (in_array($status, mg_share_market_approval_pending_statuses(), true)) $summary['pending']++;
            elseif (array_key_exists($status, $summary)) $summary[$status]++;
            if (!empty($item['escalated'])) $summary['escalated']++;
            $approvedIds = array_map(static fn(array $a): int => (int)$a['actor_user_id'], $item['approvals']);
            $pending = in_array($status, mg_share_market_approval_pending_statuses(), true);
            $item['permissions'] = [
                'can_approve' => $pending && $viewerId !== (int)$item['requester_user_id'] && !in_array($viewerId, $approvedIds, true),
                'can_reject' => $pending && $viewerId !== (int)$item['requester_user_id'],
                'can_cancel' => $pending && ($viewerId === (int)$item['requester_user_id'] || $isSuper),
                'can_escalate' => $pending,
                'can_request_freeze' => $pending && $isSuper,
                'can_record_expiry' => $status === 'expired' && !array_filter($item['timeline'], static fn(array $t): bool => ($t['event_type'] ?? '') === 'share_market.approval.expired'),
                'can_execute' => false,
            ];
        }
        unset($item);
        return [
            'items' => $items,
            'summary' => $summary,
            'viewer_user_id' => $viewerId,
            'viewer_is_super_admin' => $isSuper,
            'storage_mode' => 'events_compatibility',
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_approval_find')) {
    function mg_share_market_approval_find(PDO $pdo, string $requestId): ?array
    {
        foreach (mg_share_market_approval_fold_events(mg_share_market_approval_fetch_events($pdo)) as $item) {
            if ((string)$item['request_id'] === $requestId) return $item;
        }
        return null;
    }
}

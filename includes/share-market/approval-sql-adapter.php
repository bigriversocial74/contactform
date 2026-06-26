<?php
declare(strict_types=1);

require_once __DIR__ . '/approval-workflow.php';
require_once __DIR__ . '/sql-adapter.php';

if (!function_exists('mg_share_market_approval_sql_request_type')) {
    function mg_share_market_approval_sql_request_type(array $manifest): string
    {
        $targetType = (string)($manifest['target_type'] ?? '');
        return match ($targetType) {
            'platform_pool' => 'platform_pool',
            'merchant_treasury' => 'treasury',
            'market_series' => 'series',
            'holder_position' => 'holder_position',
            'dave_score' => 'dave_score',
            default => 'participant',
        };
    }
}

if (!function_exists('mg_share_market_approval_sql_request_payload')) {
    function mg_share_market_approval_sql_request_payload(array $row, array $decisions, array $viewer): array
    {
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        $timeline = [[
            'event_type' => 'share_market.approval.requested',
            'actor_user_id' => (int)$row['requester_user_id'],
            'created_at' => (string)$row['created_at'],
            'note' => 'Approval request created.',
            'payload_hash' => (string)$row['payload_hash'],
            'actor' => ['id' => (int)$row['requester_user_id'], 'name' => 'User ' . (int)$row['requester_user_id'], 'email' => ''],
        ]];
        $approvals = [];
        $rejection = null;
        $cancellation = null;
        $escalated = false;
        $freezeRequested = false;
        foreach ($decisions as $decision) {
            $eventType = 'share_market.approval.' . match ((string)$decision['decision']) {
                'approve' => 'approved',
                'reject' => 'rejected',
                'cancel' => 'cancelled',
                'escalate' => 'escalated',
                'request_freeze' => 'freeze_requested',
                'record_expiry' => 'expired',
                default => (string)$decision['decision'],
            };
            $actor = ['id' => (int)$decision['actor_user_id'], 'name' => 'User ' . (int)$decision['actor_user_id'], 'email' => ''];
            $timeline[] = [
                'event_type' => $eventType,
                'actor_user_id' => (int)$decision['actor_user_id'],
                'created_at' => (string)$decision['created_at'],
                'note' => (string)$decision['note'],
                'payload_hash' => (string)$decision['payload_hash'],
                'actor' => $actor,
            ];
            if ($decision['decision'] === 'approve') $approvals[] = ['actor_user_id' => (int)$decision['actor_user_id'], 'created_at' => (string)$decision['created_at'], 'note' => (string)$decision['note'], 'actor' => $actor];
            if ($decision['decision'] === 'reject') $rejection = ['actor_user_id' => (int)$decision['actor_user_id'], 'note' => (string)$decision['note'], 'created_at' => (string)$decision['created_at']];
            if ($decision['decision'] === 'cancel') $cancellation = ['actor_user_id' => (int)$decision['actor_user_id'], 'note' => (string)$decision['note'], 'created_at' => (string)$decision['created_at']];
            if ($decision['decision'] === 'escalate') $escalated = true;
            if ($decision['decision'] === 'request_freeze') $freezeRequested = true;
        }
        $status = (string)$row['status'];
        if (in_array($status, mg_share_market_approval_pending_statuses(), true) && !empty($row['expires_at']) && strtotime((string)$row['expires_at']) <= time()) $status = 'expired';
        $viewerId = (int)($viewer['id'] ?? 0);
        $approvedIds = array_map(static fn(array $approval): int => (int)$approval['actor_user_id'], $approvals);
        $isSuper = mg_share_market_admin_is_super_admin($viewer);
        $pending = in_array($status, mg_share_market_approval_pending_statuses(), true);
        return [
            'id' => (int)$row['id'],
            'request_id' => (string)$row['public_id'],
            'status' => $status,
            'manifest' => $manifest,
            'projection' => $projection,
            'requester_user_id' => (int)$row['requester_user_id'],
            'requester' => ['id' => (int)$row['requester_user_id'], 'name' => 'User ' . (int)$row['requester_user_id'], 'email' => ''],
            'required_approvals' => (int)$row['required_approvals'],
            'approval_count' => count($approvals),
            'approvals' => $approvals,
            'rejection' => $rejection,
            'cancellation' => $cancellation,
            'escalated' => $escalated,
            'freeze_requested' => $freezeRequested,
            'created_at' => (string)$row['created_at'],
            'expires_at' => (string)($row['expires_at'] ?? ''),
            'last_event_hash' => (string)$row['payload_hash'],
            'timeline' => $timeline,
            'permissions' => [
                'can_approve' => $pending && $viewerId !== (int)$row['requester_user_id'] && !in_array($viewerId, $approvedIds, true),
                'can_reject' => $pending && $viewerId !== (int)$row['requester_user_id'],
                'can_cancel' => $pending && ($viewerId === (int)$row['requester_user_id'] || $isSuper),
                'can_escalate' => $pending,
                'can_request_freeze' => $pending && $isSuper,
                'can_record_expiry' => $status === 'expired',
                'can_execute' => false,
            ],
        ];
    }
}

if (!function_exists('mg_share_market_approval_sql_queue')) {
    function mg_share_market_approval_sql_queue(PDO $pdo, array $viewer): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $stmt = $pdo->query('SELECT * FROM share_market_approval_requests ORDER BY created_at DESC,id DESC LIMIT 500');
        $requests = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $items = [];
        $summary = ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'cancelled'=>0,'expired'=>0,'escalated'=>0];
        foreach ($requests ?: [] as $row) {
            $stmt = $pdo->prepare('SELECT * FROM share_market_approval_decisions WHERE approval_request_id=? ORDER BY created_at ASC,id ASC');
            $stmt->execute([(int)$row['id']]);
            $item = mg_share_market_approval_sql_request_payload($row, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], $viewer);
            $items[] = $item;
            $summary['total']++;
            $status = (string)$item['status'];
            if (in_array($status, mg_share_market_approval_pending_statuses(), true)) $summary['pending']++;
            elseif (isset($summary[$status])) $summary[$status]++;
            if (!empty($item['escalated'])) $summary['escalated']++;
        }
        return ['items'=>$items,'summary'=>$summary,'viewer_user_id'=>(int)($viewer['id'] ?? 0),'viewer_is_super_admin'=>mg_share_market_admin_is_super_admin($viewer),'storage_mode'=>'share_market_sql','execution_enabled'=>false];
    }
}

if (!function_exists('mg_share_market_approval_sql_create_request')) {
    function mg_share_market_approval_sql_create_request(PDO $pdo, array $manifest, array $projection, array $definition, array $user): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $requestId = mg_share_market_sql_public_id();
        $payloadHash = (string)$manifest['payload_hash'];
        $stmt = $pdo->prepare('SELECT id FROM share_market_approval_requests WHERE payload_hash=? LIMIT 1');
        $stmt->execute([$payloadHash]);
        if ($stmt->fetchColumn()) throw new DomainException('This validated manifest is already in the approval queue.');
        $stmt = $pdo->prepare('INSERT INTO share_market_approval_requests (public_id,request_type,action_key,event_type,target_type,target_public_id,requester_user_id,status,risk_level,required_approvals,expires_at,manifest_json,projection_json,payload_hash,previous_event_hash,execution_enabled,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 1 DAY),?,?,?,?,0,NOW())');
        $stmt->execute([
            $requestId,
            mg_share_market_approval_sql_request_type($manifest),
            (string)$manifest['action'],
            (string)$manifest['event_type'],
            (string)$manifest['target_type'],
            (string)$manifest['target_id'],
            (int)$user['id'],
            'awaiting_first_approval',
            (string)($definition['risk'] ?? $manifest['risk'] ?? 'medium'),
            (int)$manifest['required_approvals'],
            mg_share_market_sql_json($manifest),
            mg_share_market_sql_json($projection),
            $payloadHash,
            null,
        ]);
        return mg_share_market_approval_sql_queue($pdo, $user);
    }
}

if (!function_exists('mg_share_market_approval_sql_record_decision')) {
    function mg_share_market_approval_sql_record_decision(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $requestId = trim((string)($input['request_id'] ?? ''));
        $decision = strtolower(trim((string)($input['decision'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));
        if ($note === '' || mb_strlen($note) > 1000) throw new InvalidArgumentException('A decision note between 1 and 1,000 characters is required.');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM share_market_approval_requests WHERE public_id=? LIMIT 1 FOR UPDATE');
            $stmt->execute([$requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new InvalidArgumentException('Approval request not found.');
            $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
            $pending = in_array((string)$row['status'], mg_share_market_approval_pending_statuses(), true);
            $viewerId = (int)$user['id'];
            if ($decision === 'approve') {
                if (!$pending) throw new DomainException('Only pending requests can be approved.');
                if ($viewerId === (int)$row['requester_user_id']) throw new DomainException('The requesting administrator cannot approve this request.');
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM share_market_approval_decisions WHERE approval_request_id=? AND actor_user_id=? AND decision='approve'");
                $stmt->execute([(int)$row['id'],$viewerId]);
                if ((int)$stmt->fetchColumn() > 0) throw new DomainException('This administrator has already approved the request.');
                if (!empty($manifest['super_admin_required']) && !mg_share_market_admin_is_super_admin($user)) throw new DomainException('This critical request requires a super administrator approver.');
            } elseif ($decision === 'reject') {
                if (!$pending) throw new DomainException('Only pending requests can be rejected.');
                if ($viewerId === (int)$row['requester_user_id']) throw new DomainException('The requesting administrator must cancel rather than reject the request.');
            } elseif ($decision === 'cancel') {
                if (!$pending) throw new DomainException('Only pending requests can be cancelled.');
                if ($viewerId !== (int)$row['requester_user_id'] && !mg_share_market_admin_is_super_admin($user)) throw new DomainException('Only the requester or a super administrator can cancel this request.');
            } elseif (in_array($decision, ['escalate','request_freeze'], true)) {
                if (!$pending) throw new DomainException('Only pending requests can receive this decision.');
                if ($decision === 'request_freeze' && !mg_share_market_admin_is_super_admin($user)) throw new DomainException('Only a super administrator can request an emergency target freeze.');
            } elseif ($decision === 'record_expiry') {
                if (empty($row['expires_at']) || strtotime((string)$row['expires_at']) > time()) throw new DomainException('This request has not expired.');
            } else {
                throw new InvalidArgumentException('Select a valid approval decision.');
            }
            $eventPayload = ['request_id'=>$requestId,'decision'=>$decision,'actor_user_id'=>$viewerId,'note'=>$note,'created_at'=>gmdate('c'),'previous_event_hash'=>(string)$row['payload_hash'],'execution_enabled'=>false];
            $hash = mg_share_market_program_canonical_hash($eventPayload);
            $stmt = $pdo->prepare('INSERT INTO share_market_approval_decisions (public_id,approval_request_id,actor_user_id,decision,note,payload_hash,previous_event_hash,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
            $stmt->execute([mg_share_market_sql_public_id(),(int)$row['id'],$viewerId,$decision,$note,$hash,(string)$row['payload_hash']]);
            $newStatus = (string)$row['status'];
            if ($decision === 'approve') {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM share_market_approval_decisions WHERE approval_request_id=? AND decision='approve'");
                $stmt->execute([(int)$row['id']]);
                $approvalCount = (int)$stmt->fetchColumn();
                $newStatus = $approvalCount >= (int)$row['required_approvals'] ? 'approved' : 'awaiting_second_approval';
                $stmt = $pdo->prepare('UPDATE share_market_approval_requests SET status=?,approval_count=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$newStatus,$approvalCount,(int)$row['id']]);
            } elseif ($decision === 'reject') $newStatus = 'rejected';
            elseif ($decision === 'cancel') $newStatus = 'cancelled';
            elseif ($decision === 'record_expiry') $newStatus = 'expired';
            if (in_array($decision, ['reject','cancel','record_expiry'], true)) {
                $stmt = $pdo->prepare('UPDATE share_market_approval_requests SET status=?,updated_at=NOW() WHERE id=?');
                $stmt->execute([$newStatus,(int)$row['id']]);
            }
            $pdo->commit();
            return mg_share_market_approval_sql_queue($pdo, $user);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

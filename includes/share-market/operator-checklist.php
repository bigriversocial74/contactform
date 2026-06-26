<?php
declare(strict_types=1);

require_once __DIR__ . '/execution-lockbox.php';

if (!function_exists('mg_share_market_operator_checklist_items')) {
    function mg_share_market_operator_checklist_items(): array
    {
        return [
            'legal_operator_review' => 'Legal/operator review placeholder',
            'redemption_utility_verified' => 'Redemption utility verified',
            'merchant_terms_reviewed' => 'Merchant terms reviewed',
            'credit_reserve_verified' => 'Credit reserve verified',
            'series_economics_reviewed' => 'Series economics reviewed',
            'fraud_risk_review' => 'Fraud/risk review',
            'support_escalation_confirmed' => 'Support/escalation contact confirmed',
        ];
    }
}

if (!function_exists('mg_share_market_operator_json')) {
    function mg_share_market_operator_json(array $value): string
    {
        return json_encode(mg_share_market_admin_canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

if (!function_exists('mg_share_market_operator_row_payload')) {
    function mg_share_market_operator_row_payload(?array $row): ?array
    {
        if (!$row) return null;
        $manifest = mg_share_market_sql_decode($row['manifest_json'] ?? null);
        $projection = mg_share_market_sql_decode($row['projection_json'] ?? null);
        return [
            'id' => (int)$row['id'],
            'public_id' => (string)$row['public_id'],
            'status' => (string)$row['status'],
            'review_status' => (string)($projection['review_status'] ?? $row['status']),
            'participant_user_id' => (int)$row['requester_user_id'],
            'target_public_id' => (string)$row['target_public_id'],
            'checks' => is_array($manifest['checks'] ?? null) ? $manifest['checks'] : [],
            'note' => (string)($manifest['final_admin_note'] ?? ''),
            'blockers' => is_array($projection['blockers'] ?? null) ? $projection['blockers'] : [],
            'completion_percent' => (int)($projection['completion_percent'] ?? 0),
            'packet_hash' => (string)($manifest['packet_hash'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_operator_latest_for_user')) {
    function mg_share_market_operator_latest_for_user(PDO $pdo, int $participantUserId): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM share_market_approval_requests WHERE request_type='operator' AND action_key='operator_checklist_review' AND requester_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 1");
            $stmt->execute([$participantUserId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return mg_share_market_operator_row_payload($row ?: null);
        } catch (Throwable) { return null; }
    }
}

if (!function_exists('mg_share_market_operator_checklist_status')) {
    function mg_share_market_operator_checklist_status(PDO $pdo, int $participantUserId): array
    {
        $lockbox = mg_share_market_lockbox_for_user($pdo, $participantUserId);
        $packet = is_array($lockbox['packet'] ?? null) ? $lockbox['packet'] : [];
        $saved = mg_share_market_operator_latest_for_user($pdo, $participantUserId);
        $checks = $saved['checks'] ?? [];
        $required = mg_share_market_operator_checklist_items();
        $normalized = [];
        foreach ($required as $key => $label) {
            $normalized[$key] = !empty($checks[$key]);
        }
        $note = trim((string)($saved['note'] ?? ''));
        $blockers = [];
        foreach ($required as $key => $label) {
            if (empty($normalized[$key])) $blockers[] = ['key'=>$key,'label'=>$label,'status'=>'missing'];
        }
        if ($note === '') $blockers[] = ['key'=>'final_admin_note','label'=>'Final admin note required','status'=>'missing'];
        if (($packet['status'] ?? '') !== 'lockbox_ready') $blockers[] = ['key'=>'lockbox_ready','label'=>'Lockbox packet ready','status'=>(string)($packet['status'] ?? 'missing')];
        $completeCount = count($required) - count(array_filter($normalized, static fn(bool $v): bool => !$v));
        if ($note !== '') $completeCount++;
        $total = count($required) + 1;
        $percent = (int)round(($completeCount / max(1, $total)) * 100);
        $complete = $percent === 100 && empty($blockers);
        return [
            'status' => $complete ? 'operator_complete_locked' : 'operator_review_in_progress',
            'completion_percent' => $percent,
            'complete' => $complete,
            'items' => $required,
            'checks' => $normalized,
            'final_admin_note' => $note,
            'blockers' => $blockers,
            'saved_review' => $saved,
            'lockbox' => $lockbox,
            'merchant_message' => $complete ? 'Operator checklist complete — launch still locked.' : 'Operator review in progress.',
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_operator_checklist_save')) {
    function mg_share_market_operator_checklist_save(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_admin_authorized($user)) throw new DomainException('Share Market Admin permission is required.');
        $participantUserId = filter_var($input['participant_user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($participantUserId === false || $participantUserId < 1) throw new InvalidArgumentException('Select a valid merchant.');
        $checksInput = is_array($input['checks'] ?? null) ? $input['checks'] : [];
        $note = trim((string)($input['final_admin_note'] ?? $input['note'] ?? ''));
        if (mb_strlen($note) > 1200) throw new InvalidArgumentException('Final admin note cannot exceed 1,200 characters.');
        $required = mg_share_market_operator_checklist_items();
        $checks = [];
        foreach ($required as $key => $label) $checks[$key] = !empty($checksInput[$key]);
        $lockbox = mg_share_market_lockbox_for_user($pdo, (int)$participantUserId);
        $packet = is_array($lockbox['packet'] ?? null) ? $lockbox['packet'] : [];
        $seriesPublicId = (string)($packet['series_public_id'] ?? '');
        $manifest = ['kind'=>'operator_checklist_review','participant_user_id'=>(int)$participantUserId,'series_public_id'=>$seriesPublicId,'packet_hash'=>(string)($packet['packet_hash'] ?? ''),'checks'=>$checks,'final_admin_note'=>$note,'reviewed_by_user_id'=>(int)$user['id'],'reviewed_at'=>gmdate('c'),'execution_enabled'=>false];
        $tempStatus = mg_share_market_operator_checklist_status_from_manifest($packet, $checks, $note);
        $projection = ['review_status'=>$tempStatus['status'],'completion_percent'=>$tempStatus['completion_percent'],'blockers'=>$tempStatus['blockers'],'execution_enabled'=>false];
        $hash = hash('sha256', mg_share_market_operator_json($manifest));
        $existing = mg_share_market_operator_latest_for_user($pdo, (int)$participantUserId);
        if ($existing) {
            $pdo->prepare("UPDATE share_market_approval_requests SET status=?,manifest_json=?,projection_json=?,payload_hash=?,updated_at=NOW() WHERE id=?")->execute([$tempStatus['complete'] ? 'approved' : 'awaiting_first_approval', mg_share_market_operator_json($manifest), mg_share_market_operator_json($projection), $hash, (int)$existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO share_market_approval_requests (public_id,request_type,action_key,event_type,target_type,target_public_id,requester_user_id,status,risk_level,required_approvals,approval_count,manifest_json,projection_json,payload_hash,execution_enabled,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,'medium',1,0,?,?,?,0,NOW(),NOW())")->execute([mg_share_market_sql_public_id(),'operator','operator_checklist_review','share_market.sql.operator_checklist_reviewed','series',$seriesPublicId,(int)$participantUserId,$tempStatus['complete'] ? 'approved' : 'awaiting_first_approval',mg_share_market_operator_json($manifest),mg_share_market_operator_json($projection),$hash]);
        }
        mg_share_market_sql_admin_event($pdo, (int)$user['id'], 'share_market.sql.operator_checklist_' . ($tempStatus['complete'] ? 'complete' : 'in_progress'), 'operator_checklist', $seriesPublicId, '', $tempStatus['status'], $note);
        if ($tempStatus['complete']) {
            mg_share_market_notify_merchant($pdo, (int)$participantUserId, (int)$user['id'], 'DAVE operator checklist complete', 'Operator checklist complete — launch still locked.', 'operator_checklist_complete.' . (int)$participantUserId . '.' . ($seriesPublicId ?: 'series'), ['target_type'=>'operator_checklist','target_public_id'=>$seriesPublicId,'share_market_status'=>'operator_complete_locked']);
        }
        return mg_share_market_operator_checklist_status($pdo, (int)$participantUserId);
    }
}

if (!function_exists('mg_share_market_operator_checklist_status_from_manifest')) {
    function mg_share_market_operator_checklist_status_from_manifest(array $packet, array $checks, string $note): array
    {
        $required = mg_share_market_operator_checklist_items();
        $blockers = [];
        foreach ($required as $key => $label) if (empty($checks[$key])) $blockers[] = ['key'=>$key,'label'=>$label,'status'=>'missing'];
        if (trim($note) === '') $blockers[] = ['key'=>'final_admin_note','label'=>'Final admin note required','status'=>'missing'];
        if (($packet['status'] ?? '') !== 'lockbox_ready') $blockers[] = ['key'=>'lockbox_ready','label'=>'Lockbox packet ready','status'=>(string)($packet['status'] ?? 'missing')];
        $completeCount = count($required) - count(array_filter($checks, static fn(bool $v): bool => !$v));
        if (trim($note) !== '') $completeCount++;
        $percent = (int)round(($completeCount / max(1, count($required) + 1)) * 100);
        $complete = $percent === 100 && empty($blockers);
        return ['status'=>$complete ? 'operator_complete_locked' : 'operator_review_in_progress','completion_percent'=>$percent,'complete'=>$complete,'blockers'=>$blockers,'execution_enabled'=>false];
    }
}

if (!function_exists('mg_share_market_operator_admin_queue')) {
    function mg_share_market_operator_admin_queue(PDO $pdo): array
    {
        $lockbox = mg_share_market_lockbox_admin_queue($pdo);
        $items = [];
        foreach (($lockbox['items'] ?? []) as $item) {
            $participantUserId = (int)($item['participant_user_id'] ?? 0);
            if ($participantUserId < 1) continue;
            $status = mg_share_market_operator_checklist_status($pdo, $participantUserId);
            $items[] = ['participant_user_id'=>$participantUserId,'merchant_label'=>(string)($item['merchant_label'] ?? 'Merchant'),'series_name'=>(string)($item['series_name'] ?? 'Controlled DAVE series'),'series_public_id'=>(string)($item['series_public_id'] ?? ''),'lockbox_status'=>(string)($item['status'] ?? ''),'operator'=>$status,'execution_enabled'=>false];
        }
        return ['items'=>$items,'summary'=>['total'=>count($items),'complete'=>count(array_filter($items, static fn(array $i): bool => !empty($i['operator']['complete']))),'in_progress'=>count(array_filter($items, static fn(array $i): bool => empty($i['operator']['complete']))),'execution_locked'=>count($items)],'execution_enabled'=>false];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/program-workflow.php';

if (!function_exists('mg_share_market_sql_public_id')) {
    function mg_share_market_sql_public_id(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}

if (!function_exists('mg_share_market_sql_series_id')) {
    function mg_share_market_sql_series_id(): string
    {
        return 'sm_' . bin2hex(random_bytes(16));
    }
}

if (!function_exists('mg_share_market_sql_json')) {
    function mg_share_market_sql_json(array $value): string
    {
        return json_encode(mg_share_market_admin_canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}

if (!function_exists('mg_share_market_sql_bool')) {
    function mg_share_market_sql_bool(mixed $value): int
    {
        return !empty($value) ? 1 : 0;
    }
}

if (!function_exists('mg_share_market_sql_decode')) {
    function mg_share_market_sql_decode(mixed $json): array
    {
        if (!is_string($json) || $json === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('mg_share_market_sql_schema_available')) {
    function mg_share_market_sql_schema_available(PDO $pdo): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'share_market_enrollments'");
            $available = (bool)($stmt && $stmt->fetchColumn());
            return $available;
        } catch (Throwable) {
            $available = false;
            return false;
        }
    }
}

if (!function_exists('mg_share_market_sql_enrollment_payload')) {
    function mg_share_market_sql_enrollment_payload(?array $row): ?array
    {
        if (!$row) return null;
        $metadata = mg_share_market_sql_decode($row['metadata_json'] ?? null);
        return [
            'id' => (int)$row['id'],
            'participant_id' => (string)$row['public_id'],
            'public_id' => (string)$row['public_id'],
            'participant_user_id' => (int)$row['participant_user_id'],
            'participant_type' => (string)$row['participant_type'],
            'legal_name' => (string)$row['legal_name'],
            'public_name' => (string)$row['public_name'],
            'website' => (string)($row['website'] ?? ''),
            'use_case' => (string)$row['use_case'],
            'utility_plan' => (string)$row['utility_plan'],
            'status' => (string)$row['status'],
            'admin_note' => (string)($row['review_note'] ?? ''),
            'submitted_at' => (string)($row['submitted_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'last_event_type' => (string)($metadata['last_event_type'] ?? 'share_market.sql.enrollment_snapshot'),
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_sql_series_payload')) {
    function mg_share_market_sql_series_payload(array $row, ?array $redemption = null): array
    {
        $metadata = mg_share_market_sql_decode($row['metadata_json'] ?? null);
        $redemptionPayload = null;
        if ($redemption) {
            $redemptionPayload = [
                'id' => (int)$redemption['id'],
                'public_id' => (string)$redemption['public_id'],
                'type' => (string)$redemption['redemption_type'],
                'title' => (string)$redemption['title'],
                'details' => (string)$redemption['details'],
                'share_cost' => (int)$redemption['share_cost'],
                'status' => (string)$redemption['status'],
            ];
        }
        return [
            'id' => (int)$row['id'],
            'series_id' => (string)$row['public_id'],
            'public_id' => (string)$row['public_id'],
            'participant_user_id' => (int)$row['participant_user_id'],
            'name' => (string)$row['name'],
            'description' => (string)$row['description'],
            'state' => (string)$row['state'],
            'supply' => (int)$row['supply'],
            'launch_price_cents' => (int)$row['launch_price_cents'],
            'currency' => (string)$row['currency'],
            'max_per_buyer' => (int)$row['max_per_buyer'],
            'redemption_enabled' => (bool)$row['redemption_enabled'],
            'resale_enabled' => (bool)$row['resale_enabled'],
            'reissue_milestone' => (string)($row['reissue_milestone'] ?? ''),
            'redemption' => $redemptionPayload,
            'admin_note' => (string)($row['review_note'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'last_event_type' => (string)($metadata['last_event_type'] ?? 'share_market.sql.series_snapshot'),
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_sql_fetch_enrollment_by_user')) {
    function mg_share_market_sql_fetch_enrollment_by_user(PDO $pdo, int $userId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM share_market_enrollments WHERE participant_user_id=? ORDER BY id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_share_market_sql_fetch_series_redemption')) {
    function mg_share_market_sql_fetch_series_redemption(PDO $pdo, int $seriesId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM share_market_series_redemptions WHERE series_id=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$seriesId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_share_market_sql_user_snapshot')) {
    function mg_share_market_sql_user_snapshot(PDO $pdo, int $userId): array
    {
        $enrollmentRow = mg_share_market_sql_fetch_enrollment_by_user($pdo, $userId);
        $stmt = $pdo->prepare('SELECT * FROM share_market_series WHERE participant_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 100');
        $stmt->execute([$userId]);
        $series = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $series[] = mg_share_market_sql_series_payload($row, mg_share_market_sql_fetch_series_redemption($pdo, (int)$row['id']));
        }
        return [
            'enrollment' => mg_share_market_sql_enrollment_payload($enrollmentRow),
            'series' => $series,
            'execution_enabled' => false,
            'storage_mode' => 'share_market_sql',
        ];
    }
}

if (!function_exists('mg_share_market_sql_submit_enrollment')) {
    function mg_share_market_sql_submit_enrollment(PDO $pdo, array $payload, int $userId): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $allowed = ['not_enrolled','interested','rejected','closed'];
        $pdo->beginTransaction();
        try {
            $existing = mg_share_market_sql_fetch_enrollment_by_user($pdo, $userId, true);
            $currentState = (string)($existing['status'] ?? 'not_enrolled');
            if (!in_array($currentState, $allowed, true)) {
                throw new DomainException('This account already has a Share Market request in progress.');
            }
            $metadata = ['source' => 'sql_adapter', 'last_event_type' => 'share_market.sql.enrollment_submitted'];
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE share_market_enrollments SET participant_type=?,legal_name=?,public_name=?,website=?,use_case=?,utility_plan=?,status='under_review',optional_participation_confirmed=1,admin_review_confirmed=1,submitted_at=NOW(),review_note=NULL,reviewed_by_user_id=NULL,metadata_json=? WHERE id=?");
                $stmt->execute([$payload['participant_type'],$payload['legal_name'],$payload['public_name'],$payload['website'],$payload['use_case'],$payload['utility_plan'],mg_share_market_sql_json($metadata),(int)$existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO share_market_enrollments (public_id,participant_user_id,participant_type,legal_name,public_name,website,use_case,utility_plan,status,optional_participation_confirmed,admin_review_confirmed,submitted_at,metadata_json) VALUES (?,?,?,?,?,?,?,?, 'under_review',1,1,NOW(),?)");
                $stmt->execute([mg_share_market_sql_public_id(),$userId,$payload['participant_type'],$payload['legal_name'],$payload['public_name'],$payload['website'],$payload['use_case'],$payload['utility_plan'],mg_share_market_sql_json($metadata)]);
            }
            $pdo->commit();
            return mg_share_market_sql_user_snapshot($pdo, $userId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('mg_share_market_sql_find_series')) {
    function mg_share_market_sql_find_series(PDO $pdo, string $seriesPublicId, int $userId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM share_market_series WHERE public_id=? AND participant_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$seriesPublicId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('mg_share_market_sql_save_series')) {
    function mg_share_market_sql_save_series(PDO $pdo, array $series, int $userId, bool $submit): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $pdo->beginTransaction();
        try {
            $enrollment = mg_share_market_sql_fetch_enrollment_by_user($pdo, $userId, true);
            if (!$enrollment) throw new DomainException('Submit a Share Market participation request before saving a series.');
            if ($submit && !in_array((string)$enrollment['status'], ['under_review','approved','active'], true)) {
                throw new DomainException('This enrollment is not eligible for series submission.');
            }
            $seriesPublicId = (string)($series['series_id'] ?? '');
            if ($seriesPublicId === '' || !preg_match('/^sm_[A-Za-z0-9]{20,64}$/', $seriesPublicId)) $seriesPublicId = mg_share_market_sql_series_id();
            $existing = mg_share_market_sql_find_series($pdo, $seriesPublicId, $userId, true);
            $state = $submit ? 'submitted' : 'draft';
            $metadata = ['source' => 'sql_adapter', 'last_event_type' => $submit ? 'share_market.sql.series_submitted' : 'share_market.sql.series_draft_saved'];
            if ($existing) {
                if (in_array((string)$existing['state'], ['approved','live','closed','frozen'], true)) throw new DomainException('This series state cannot be edited from the draft builder.');
                $stmt = $pdo->prepare('UPDATE share_market_series SET name=?,description=?,state=?,supply=?,launch_price_cents=?,max_per_buyer=?,redemption_enabled=?,resale_enabled=?,reissue_milestone=?,submitted_at=IF(?="submitted",NOW(),submitted_at),review_note=NULL,metadata_json=? WHERE id=?');
                $stmt->execute([$series['name'],$series['description'],$state,(int)$series['supply'],(int)$series['launch_price_cents'],(int)$series['max_per_buyer'],mg_share_market_sql_bool($series['redemption_enabled']),mg_share_market_sql_bool($series['resale_enabled']),(string)$series['reissue_milestone'],$state,mg_share_market_sql_json($metadata),(int)$existing['id']]);
                $seriesId = (int)$existing['id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO share_market_series (public_id,enrollment_id,participant_user_id,name,description,state,supply,launch_price_cents,currency,max_per_buyer,redemption_enabled,resale_enabled,reissue_milestone,submitted_at,metadata_json) VALUES (?,?,?,?,?,?,?,?,"USD",?,?,?,?,IF(?="submitted",NOW(),NULL),?)');
                $stmt->execute([$seriesPublicId,(int)$enrollment['id'],$userId,$series['name'],$series['description'],$state,(int)$series['supply'],(int)$series['launch_price_cents'],(int)$series['max_per_buyer'],mg_share_market_sql_bool($series['redemption_enabled']),mg_share_market_sql_bool($series['resale_enabled']),(string)$series['reissue_milestone'],$state,mg_share_market_sql_json($metadata)]);
                $seriesId = (int)$pdo->lastInsertId();
            }
            $redemption = $series['redemption'];
            $redemptionStatus = $submit ? 'submitted' : 'draft';
            $existingRedemption = mg_share_market_sql_fetch_series_redemption($pdo, $seriesId);
            if ($existingRedemption) {
                $stmt = $pdo->prepare('UPDATE share_market_series_redemptions SET redemption_type=?,title=?,details=?,share_cost=?,status=? WHERE id=?');
                $stmt->execute([$redemption['type'],$redemption['title'],$redemption['details'],(int)$redemption['share_cost'],$redemptionStatus,(int)$existingRedemption['id']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO share_market_series_redemptions (public_id,series_id,redemption_type,title,details,share_cost,status) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([mg_share_market_sql_public_id(),$seriesId,$redemption['type'],$redemption['title'],$redemption['details'],(int)$redemption['share_cost'],$redemptionStatus]);
            }
            $pdo->commit();
            $stmt = $pdo->prepare('SELECT * FROM share_market_series WHERE id=? LIMIT 1');
            $stmt->execute([$seriesId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['series' => mg_share_market_sql_series_payload($row, mg_share_market_sql_fetch_series_redemption($pdo, $seriesId)), 'snapshot' => mg_share_market_sql_user_snapshot($pdo, $userId)];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('mg_share_market_sql_admin_review_snapshot')) {
    function mg_share_market_sql_admin_review_snapshot(PDO $pdo): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $enrollments = [];
        $stmt = $pdo->query("SELECT * FROM share_market_enrollments WHERE status='under_review' ORDER BY submitted_at ASC,id ASC LIMIT 200");
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) $enrollments[] = mg_share_market_sql_enrollment_payload($row);
        $series = [];
        $stmt = $pdo->query("SELECT * FROM share_market_series WHERE state IN ('submitted','changes_requested') ORDER BY updated_at ASC,id ASC LIMIT 200");
        foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) $series[] = mg_share_market_sql_series_payload($row, mg_share_market_sql_fetch_series_redemption($pdo, (int)$row['id']));
        return ['enrollments' => $enrollments, 'series' => $series, 'summary' => ['enrollments' => count($enrollments), 'series' => count($series), 'execution_enabled' => false], 'storage_mode' => 'share_market_sql'];
    }
}

if (!function_exists('mg_share_market_sql_admin_event')) {
    function mg_share_market_sql_admin_event(PDO $pdo, int $actorUserId, string $eventType, string $targetType, string $targetPublicId, ?string $oldState, ?string $newState, string $note): void
    {
        $payload = ['actor_user_id'=>$actorUserId,'event_type'=>$eventType,'target_type'=>$targetType,'target_public_id'=>$targetPublicId,'old_state'=>$oldState,'new_state'=>$newState,'note'=>$note,'created_at'=>gmdate('c'),'execution_enabled'=>false];
        $hash = mg_share_market_program_canonical_hash($payload);
        $stmt = $pdo->prepare('INSERT INTO share_market_admin_events (public_id,actor_user_id,event_type,target_type,target_public_id,old_state,new_state,note,payload_hash,payload_json,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([mg_share_market_sql_public_id(),$actorUserId,$eventType,$targetType,$targetPublicId,$oldState,$newState,$note,$hash,mg_share_market_sql_json($payload)]);
    }
}

if (!function_exists('mg_share_market_sql_record_program_decision')) {
    function mg_share_market_sql_record_program_decision(PDO $pdo, array $input, array $user): array
    {
        if (!mg_share_market_sql_schema_available($pdo)) throw new RuntimeException('Buy-In SQL schema is not installed.');
        $actorId = (int)($user['id'] ?? 0);
        $targetType = strtolower(trim((string)($input['target_type'] ?? '')));
        $decision = strtolower(trim((string)($input['decision'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));
        if ($note === '' || mb_strlen($note) > 1000) throw new InvalidArgumentException('Decision note is required and cannot exceed 1,000 characters.');
        $pdo->beginTransaction();
        try {
            if ($targetType === 'enrollment') {
                $participantId = trim((string)($input['participant_id'] ?? ''));
                $newState = match ($decision) {'approve'=>'approved','reject'=>'rejected','pause'=>'paused','suspend'=>'suspended',default=>null};
                if (!$newState) throw new InvalidArgumentException('Select a valid enrollment review decision.');
                $stmt = $pdo->prepare('SELECT * FROM share_market_enrollments WHERE public_id=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$participantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new InvalidArgumentException('Enrollment review target was not found.');
                $oldState = (string)$row['status'];
                $timeColumn = $newState === 'approved' ? 'approved_at' : ($newState === 'rejected' ? 'rejected_at' : ($newState === 'paused' ? 'paused_at' : 'suspended_at'));
                $stmt = $pdo->prepare("UPDATE share_market_enrollments SET status=?, reviewed_by_user_id=?, review_note=?, {$timeColumn}=NOW() WHERE id=?");
                $stmt->execute([$newState,$actorId,$note,(int)$row['id']]);
                mg_share_market_sql_admin_event($pdo,$actorId,'share_market.sql.enrollment_' . $newState,'enrollment',$participantId,$oldState,$newState,$note);
            } elseif ($targetType === 'series') {
                $seriesId = trim((string)($input['series_id'] ?? ''));
                $newState = match ($decision) {'approve'=>'approved','reject'=>'rejected','changes'=>'changes_requested','pause'=>'paused',default=>null};
                if (!$newState) throw new InvalidArgumentException('Select a valid series review decision.');
                $stmt = $pdo->prepare('SELECT * FROM share_market_series WHERE public_id=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$seriesId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new InvalidArgumentException('Series review target was not found.');
                $oldState = (string)$row['state'];
                if ($oldState === 'live') throw new DomainException('Live series execution remains locked.');
                $timeColumn = $newState === 'approved' ? 'approved_at' : ($newState === 'paused' ? 'paused_at' : 'updated_at');
                $stmt = $pdo->prepare("UPDATE share_market_series SET state=?, reviewed_by_user_id=?, review_note=?, {$timeColumn}=NOW() WHERE id=?");
                $stmt->execute([$newState,$actorId,$note,(int)$row['id']]);
                if (in_array($newState, ['approved','paused'], true)) {
                    $redemptionState = $newState === 'approved' ? 'approved' : 'paused';
                    $stmt = $pdo->prepare('UPDATE share_market_series_redemptions SET status=? WHERE series_id=?');
                    $stmt->execute([$redemptionState,(int)$row['id']]);
                }
                mg_share_market_sql_admin_event($pdo,$actorId,'share_market.sql.series_' . $newState,'series',$seriesId,$oldState,$newState,$note);
            } else {
                throw new InvalidArgumentException('Select enrollment or series review.');
            }
            $pdo->commit();
            return mg_share_market_sql_admin_review_snapshot($pdo);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}

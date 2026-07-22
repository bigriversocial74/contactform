<?php
declare(strict_types=1);

function mg_creator_campaign_tracking_source_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    ?int $creatorUserId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('source_id is required.');
    $sql = "SELECT s.*,p.public_id participant_public_id,p.status participant_status,
                   cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,
                   cp.display_name creator_name,mw.display_name merchant_name
            FROM creator_campaign_tracking_sources s
            INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
            INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
            INNER JOIN creator_profiles cp ON cp.id=p.creator_profile_id
            INNER JOIN merchant_workspaces mw ON mw.id=cc.workspace_id
            WHERE s.public_id=?";
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    if ($creatorUserId !== null) { $sql .= ' AND s.creator_user_id=?'; $params[] = $creatorUserId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign tracking source not found.');
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? null);
    unset($row['metadata_json']);
    return $row;
}

function mg_creator_campaign_tracking_source_by_code(PDO $pdo, string $code, bool $forUpdate = false): array
{
    $code = strtolower(trim($code));
    if (preg_match('/^[a-f0-9]{32}$/', $code) !== 1) throw new RuntimeException('Creator tracking link not found.');
    $sql = "SELECT s.*,p.public_id participant_public_id,p.status participant_status,
                   cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id,cc.status campaign_status
            FROM creator_campaign_tracking_sources s
            INNER JOIN creator_campaign_participants p ON p.id=s.participant_id
            INNER JOIN creator_campaigns cc ON cc.id=s.campaign_id
            WHERE s.tracking_code=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (string) $row['status'] !== 'active' || (string) $row['participant_status'] !== 'active'
        || !in_array((string) $row['campaign_status'], ['scheduled','active'], true)) {
        throw new RuntimeException('Creator tracking link not found.');
    }
    return $row;
}

function mg_creator_campaign_tracking_event_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('event_id is required.');
    $sql = "SELECT e.*,s.public_id source_public_id,s.label source_label,p.public_id participant_public_id,
                   cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id
            FROM creator_campaign_tracking_events e
            LEFT JOIN creator_campaign_tracking_sources s ON s.id=e.source_id
            LEFT JOIN creator_campaign_participants p ON p.id=e.participant_id
            INNER JOIN creator_campaigns cc ON cc.id=e.campaign_id
            WHERE e.public_id=?";
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign tracking event not found.');
    $row['metadata'] = mg_creator_campaign_participation_decode_json($row['metadata_json'] ?? null);
    $row['risk_flags'] = mg_creator_campaign_participation_decode_json($row['risk_flags_json'] ?? null);
    unset($row['metadata_json'],$row['risk_flags_json']);
    return $row;
}

function mg_creator_campaign_attribution_by_public_id(
    PDO $pdo,
    string $publicId,
    ?int $workspaceId = null,
    bool $forUpdate = false
): array {
    $publicId = trim($publicId);
    if ($publicId === '') throw new InvalidArgumentException('attribution_id is required.');
    $sql = "SELECT a.*,e.public_id conversion_event_public_id,e.event_type conversion_type,e.occurred_at,
                   s.public_id source_public_id,s.label source_label,p.public_id participant_public_id,
                   cc.public_id campaign_public_id,cc.title campaign_title,cc.workspace_id
            FROM creator_campaign_attributions a
            INNER JOIN creator_campaign_tracking_events e ON e.id=a.conversion_event_id
            LEFT JOIN creator_campaign_tracking_sources s ON s.id=a.source_id
            LEFT JOIN creator_campaign_participants p ON p.id=a.participant_id
            INNER JOIN creator_campaigns cc ON cc.id=a.campaign_id
            WHERE a.public_id=?";
    $params = [$publicId];
    if ($workspaceId !== null) { $sql .= ' AND cc.workspace_id=?'; $params[] = $workspaceId; }
    $sql .= ' LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('Creator campaign attribution not found.');
    return $row;
}

function mg_creator_campaign_tracking_risk(
    PDO $pdo,
    int $campaignId,
    ?int $sourceId,
    string $eventType,
    ?string $visitorHash,
    ?string $requestHash,
    string $occurredAt,
    int $clickWindowDays = 1
): array {
    $flags = [];
    $score = 0;
    $status = 'accepted';
    $unique = 1;

    if ($sourceId !== null && $visitorHash !== null) {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM creator_campaign_tracking_events
             WHERE source_id=? AND visitor_hash=? AND event_type=? AND occurred_at>=DATE_SUB(?,INTERVAL 30 SECOND)"
        );
        $stmt->execute([$sourceId,$visitorHash,$eventType,$occurredAt]);
        if ((int) $stmt->fetchColumn() > 0) {
            $flags[] = 'rapid_replay';
            $score += 80;
            $status = 'duplicate';
            $unique = 0;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM creator_campaign_tracking_events
             WHERE campaign_id=? AND visitor_hash=? AND occurred_at>=DATE_SUB(?,INTERVAL 1 HOUR)"
        );
        $stmt->execute([$campaignId,$visitorHash,$occurredAt]);
        if ((int) $stmt->fetchColumn() >= 20) {
            $flags[] = 'high_velocity';
            $score += 40;
            if ($status === 'accepted') $status = 'suspect';
        }

        $uniqueSince = gmdate('Y-m-d H:i:s', strtotime($occurredAt) - (max(1, min(365, $clickWindowDays)) * 86400));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM creator_campaign_tracking_events
             WHERE source_id=? AND visitor_hash=? AND event_type IN ('click','landing_view')
               AND occurred_at>=? AND occurred_at<=?"
        );
        $stmt->execute([$sourceId,$visitorHash,$uniqueSince,$occurredAt]);
        if ((int) $stmt->fetchColumn() > 0) $unique = 0;
    }

    if ($requestHash !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM creator_campaign_tracking_events WHERE request_hash=?");
        $stmt->execute([$requestHash]);
        if ((int) $stmt->fetchColumn() > 0) {
            $flags[] = 'request_replay';
            $score += 100;
            $status = 'duplicate';
            $unique = 0;
        }
    }

    return [
        'status' => $status,
        'is_unique' => $unique,
        'risk_score' => min(100, $score),
        'risk_flags' => array_values(array_unique($flags)),
    ];
}

function mg_creator_campaign_tracking_insert_event(PDO $pdo, array $source, array $input): array
{
    $eventType = strtolower(trim((string) ($input['event_type'] ?? '')));
    if (!in_array($eventType, mg_creator_campaign_tracking_event_types(), true)) {
        throw new InvalidArgumentException('event_type is invalid.');
    }
    $eventKey = mg_creator_campaign_tracking_event_key($input['event_key'] ?? '');
    $occurredAt = mg_creator_campaign_tracking_occurred_at($input['occurred_at'] ?? null);
    $sessionHash = mg_creator_campaign_tracking_hash(isset($input['session_key']) ? (string) $input['session_key'] : null, 'session');
    $visitorHash = mg_creator_campaign_tracking_hash(isset($input['visitor_key']) ? (string) $input['visitor_key'] : null, 'visitor');
    $requestHash = mg_creator_campaign_tracking_hash(isset($input['request_key']) ? (string) $input['request_key'] : null, 'request');
    $metadata = mg_creator_campaign_tracking_metadata($input['metadata'] ?? []);
    $referrerHost = trim((string) ($input['referrer_host'] ?? ''));
    if ($referrerHost !== '') $referrerHost = mb_substr(strtolower($referrerHost), 0, 255);
    else $referrerHost = null;
    $targetPath = isset($input['target_path'])
        ? mg_creator_campaign_tracking_internal_path($input['target_path'], 'target_path')
        : mg_creator_campaign_tracking_internal_path($source['destination_path'] ?? '/', 'target_path');

    $sourceId = isset($source['id']) && (int) $source['id'] > 0 ? (int) $source['id'] : null;
    $participantId = isset($source['participant_id']) && (int) $source['participant_id'] > 0 ? (int) $source['participant_id'] : null;
    $creatorUserId = isset($source['creator_user_id']) && (int) $source['creator_user_id'] > 0 ? (int) $source['creator_user_id'] : null;
    $risk = mg_creator_campaign_tracking_risk(
        $pdo,
        (int) $source['campaign_id'],
        $sourceId,
        $eventType,
        $visitorHash,
        $requestHash,
        $occurredAt,
        (int) ($source['click_window_days'] ?? 1)
    );

    $publicId = mg_creator_campaign_public_id('ccte');
    $stmt = $pdo->prepare(
        "INSERT INTO creator_campaign_tracking_events
         (public_id,campaign_id,source_id,participant_id,creator_user_id,event_type,event_key,session_hash,visitor_hash,request_hash,target_path,referrer_host,metadata_json,status,is_unique,risk_score,risk_flags_json,occurred_at,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
    );
    $stmt->execute([
        $publicId,(int)$source['campaign_id'],$sourceId,$participantId,
        $creatorUserId,$eventType,$eventKey,$sessionHash,$visitorHash,$requestHash,
        $targetPath,$referrerHost,$metadata === [] ? null : mg_creator_campaign_json_encode($metadata),
        $risk['status'],$risk['is_unique'],$risk['risk_score'],
        $risk['risk_flags'] === [] ? null : mg_creator_campaign_json_encode($risk['risk_flags']),
        $occurredAt,
    ]);
    return mg_creator_campaign_tracking_event_by_public_id($pdo, $publicId);
}

function mg_creator_campaign_attribution_audit(
    PDO $pdo,
    array $attribution,
    string $eventType,
    ?int $actorUserId,
    ?int $fromSourceId,
    ?int $toSourceId,
    ?string $reason,
    array $context = []
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO creator_campaign_attribution_events
         (public_id,attribution_id,conversion_event_id,actor_user_id,event_type,from_source_id,to_source_id,reason,context_json,created_at)
         VALUES (?,?,?,?,?,?,?,?,?,NOW())"
    );
    $stmt->execute([
        mg_creator_campaign_public_id('ccae'),(int)$attribution['id'],(int)$attribution['conversion_event_id'],
        $actorUserId,$eventType,$fromSourceId,$toSourceId,$reason,
        $context === [] ? null : mg_creator_campaign_json_encode($context),
    ]);
}

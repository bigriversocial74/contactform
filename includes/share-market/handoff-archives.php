<?php
declare(strict_types=1);

require_once __DIR__ . '/preflight-handoff.php';

if (!function_exists('mg_share_market_handoff_archives_ready')) {
    function mg_share_market_handoff_archives_ready(PDO $pdo): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE 'share_market_handoff_archives'");
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('mg_share_market_handoff_hash')) {
    function mg_share_market_handoff_hash(array $handoff): string
    {
        $copy = $handoff;
        unset($copy['generated_at']);
        return hash('sha256', mg_share_market_sql_json($copy));
    }
}

if (!function_exists('mg_share_market_handoff_note')) {
    function mg_share_market_handoff_note($value): string
    {
        $note = trim((string)$value);
        if (mb_strlen($note) > 2000) throw new InvalidArgumentException('Reviewer note is too long.');
        return $note;
    }
}

if (!function_exists('mg_share_market_handoff_row')) {
    function mg_share_market_handoff_row(array $row, array $current): array
    {
        $row['handoff_json'] = mg_share_market_sql_decode($row['handoff_json'] ?? null, []);
        $currentHash = mg_share_market_handoff_hash($current);
        $savedHash = (string)($row['handoff_hash'] ?? '');
        $row['drift'] = [
            'archived_handoff_hash' => $savedHash,
            'current_handoff_hash' => $currentHash,
            'matches_current' => $savedHash !== '' && hash_equals($savedHash, $currentHash),
            'drift_status' => $savedHash === '' ? 'unknown' : (hash_equals($savedHash, $currentHash) ? 'matching' : 'drifted'),
        ];
        return $row;
    }
}

if (!function_exists('mg_share_market_handoff_archives')) {
    function mg_share_market_handoff_archives(PDO $pdo, string $attemptId, array $actor = []): array
    {
        if (!mg_share_market_handoff_archives_ready($pdo)) throw new RuntimeException('Buy-In handoff archive schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $current = mg_share_market_preflight_handoff($pdo, $attemptId, $actor);
        $stmt = $pdo->prepare('SELECT h.*,a.public_id AS acknowledgement_public_id,c.public_id AS candidate_public_id,u.email AS created_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS created_by_name FROM share_market_handoff_archives h LEFT JOIN share_market_evidence_acknowledgements a ON a.id=h.acknowledgement_id LEFT JOIN share_market_evidence_candidates c ON c.id=h.evidence_candidate_id LEFT JOIN users u ON u.id=h.created_by_user_id WHERE h.execution_attempt_id=? ORDER BY h.created_at DESC,h.id DESC');
        $stmt->execute([(int)$attempt['id']]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = mg_share_market_handoff_row($row, $current);
        return ['attempt_id'=>$attemptId,'current_handoff_hash'=>mg_share_market_handoff_hash($current),'items'=>$items,'domain_mutations_performed'=>false];
    }
}

if (!function_exists('mg_share_market_save_handoff_archive')) {
    function mg_share_market_save_handoff_archive(PDO $pdo, string $attemptId, array $actor, array $input = []): array
    {
        if (!mg_share_market_handoff_archives_ready($pdo)) throw new RuntimeException('Buy-In handoff archive schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $handoff = mg_share_market_preflight_handoff($pdo, $attemptId, $actor);
        $note = mg_share_market_handoff_note($input['reviewer_note'] ?? '');
        $hash = mg_share_market_handoff_hash($handoff);
        $ackId = null;
        $candidateId = null;
        $ackPublic = (string)($handoff['acknowledgement']['public_id'] ?? '');
        if ($ackPublic !== '') {
            $lookup = $pdo->prepare('SELECT id,evidence_candidate_id FROM share_market_evidence_acknowledgements WHERE public_id=? AND execution_attempt_id=? LIMIT 1');
            $lookup->execute([$ackPublic, (int)$attempt['id']]);
            $row = $lookup->fetch(PDO::FETCH_ASSOC) ?: [];
            $ackId = isset($row['id']) ? (int)$row['id'] : null;
            $candidateId = isset($row['evidence_candidate_id']) ? (int)$row['evidence_candidate_id'] : null;
        }
        $publicId = mg_share_market_execution_audit_public_id();
        $stmt = $pdo->prepare('INSERT INTO share_market_handoff_archives (public_id,execution_attempt_id,approval_request_id,acknowledgement_id,evidence_candidate_id,handoff_hash,handoff_json,handoff_ready,acknowledged_package_hash,current_package_hash,drift_status,reviewer_note,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$publicId,(int)$attempt['id'],(int)$attempt['approval_request_id'],$ackId,$candidateId,$hash,mg_share_market_sql_json($handoff),(bool)($handoff['handoff_ready'] ?? false) ? 1 : 0,(string)($handoff['hashes']['acknowledged_package_hash'] ?? '') ?: null,(string)($handoff['hashes']['current_package_hash'] ?? '') ?: null,(string)($handoff['hashes']['drift_status'] ?? 'unknown'),$note !== '' ? $note : null,(int)($actor['id'] ?? 0)]);
        return ['archive_id'=>$publicId,'attempt_id'=>$attemptId,'handoff_hash'=>$hash,'handoff_ready'=>(bool)($handoff['handoff_ready'] ?? false),'domain_mutations_performed'=>false];
    }
}

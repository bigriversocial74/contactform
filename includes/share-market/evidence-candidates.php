<?php
declare(strict_types=1);

require_once __DIR__ . '/evidence-export.php';

if (!function_exists('mg_share_market_candidates_schema_available')) {
    function mg_share_market_candidates_schema_available(PDO $pdo): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE 'share_market_evidence_candidates'");
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('mg_share_market_candidate_note')) {
    function mg_share_market_candidate_note($value, int $max = 2000): string
    {
        $note = trim((string)$value);
        if (mb_strlen($note) > $max) throw new InvalidArgumentException('Candidate note is too long.');
        return $note;
    }
}

if (!function_exists('mg_share_market_candidate_public_id')) {
    function mg_share_market_candidate_public_id(): string
    {
        return mg_share_market_execution_audit_public_id();
    }
}

if (!function_exists('mg_share_market_candidate_fetch_attempt')) {
    function mg_share_market_candidate_fetch_attempt(PDO $pdo, string $attemptId): array
    {
        $attempt = mg_share_market_audit_review_fetch_attempt($pdo, $attemptId);
        if (!$attempt) throw new InvalidArgumentException('Audit attempt not found.');
        return $attempt;
    }
}

if (!function_exists('mg_share_market_candidate_compare')) {
    function mg_share_market_candidate_compare(array $row, array $currentExport): array
    {
        $candidateHash = (string)($row['package_hash'] ?? '');
        $currentHash = (string)($currentExport['package_hash'] ?? '');
        return [
            'candidate_hash' => $candidateHash,
            'current_hash' => $currentHash,
            'matches_current' => $candidateHash !== '' && $candidateHash === $currentHash,
            'comparison_status' => $candidateHash === '' || $currentHash === '' ? 'unknown' : ($candidateHash === $currentHash ? 'matching' : 'drifted'),
        ];
    }
}

if (!function_exists('mg_share_market_candidate_decode')) {
    function mg_share_market_candidate_decode(array $row, array $currentExport = []): array
    {
        $row['package_json'] = mg_share_market_sql_decode($row['package_json'] ?? null);
        if ($currentExport) $row['comparison'] = mg_share_market_candidate_compare($row, $currentExport);
        return $row;
    }
}

if (!function_exists('mg_share_market_candidate_list')) {
    function mg_share_market_candidate_list(PDO $pdo, string $attemptId, array $actor = []): array
    {
        if (!mg_share_market_candidates_schema_available($pdo)) throw new RuntimeException('Buy-In evidence candidate schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $currentExport = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $stmt = $pdo->prepare('SELECT c.*,u.email AS created_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS created_by_name FROM share_market_evidence_candidates c LEFT JOIN users u ON u.id=c.created_by_user_id WHERE c.execution_attempt_id=? ORDER BY c.created_at DESC,c.id DESC');
        $stmt->execute([(int)$attempt['id']]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = mg_share_market_candidate_decode($row, $currentExport);
        return [
            'attempt_id' => $attemptId,
            'current_package_hash' => (string)($currentExport['package_hash'] ?? ''),
            'items' => $items,
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_candidate_record')) {
    function mg_share_market_candidate_record(PDO $pdo, string $attemptId, array $actor, array $input = []): array
    {
        if (!mg_share_market_candidates_schema_available($pdo)) throw new RuntimeException('Buy-In evidence candidate schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $export = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $hash = (string)($export['package_hash'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) throw new RuntimeException('Evidence package hash is invalid.');
        $note = mg_share_market_candidate_note($input['reviewer_note'] ?? '');
        $payloadJson = mg_share_market_sql_json($export);
        $pdo->beginTransaction();
        try {
            $sup = $pdo->prepare("UPDATE share_market_evidence_candidates SET status='superseded',superseded_at=NOW(),updated_at=NOW() WHERE execution_attempt_id=? AND status='active'");
            $sup->execute([(int)$attempt['id']]);
            $stmt = $pdo->prepare('INSERT INTO share_market_evidence_candidates (public_id,execution_attempt_id,approval_request_id,package_hash,package_json,status,reviewer_note,created_by_user_id) VALUES (?,?,?,?,?,?,?,?)');
            $publicId = mg_share_market_candidate_public_id();
            $stmt->execute([
                $publicId,
                (int)$attempt['id'],
                (int)$attempt['approval_request_id'],
                $hash,
                $payloadJson,
                'active',
                $note !== '' ? $note : null,
                (int)($actor['id'] ?? 0),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return [
            'candidate_id' => $publicId,
            'attempt_id' => $attemptId,
            'package_hash' => $hash,
            'status' => 'active',
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_candidate_revoke')) {
    function mg_share_market_candidate_revoke(PDO $pdo, string $attemptId, string $candidateId, array $actor, array $input = []): array
    {
        if (!mg_share_market_candidates_schema_available($pdo)) throw new RuntimeException('Buy-In evidence candidate schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        if ($candidateId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $candidateId) !== 1) throw new InvalidArgumentException('Candidate identifier is invalid.');
        $note = mg_share_market_candidate_note($input['reviewer_note'] ?? '');
        $stmt = $pdo->prepare("UPDATE share_market_evidence_candidates SET status='revoked',revoked_at=NOW(),updated_at=NOW(),reviewer_note=COALESCE(NULLIF(?,''),reviewer_note) WHERE public_id=? AND execution_attempt_id=? AND status<>'revoked'");
        $stmt->execute([$note, $candidateId, (int)$attempt['id']]);
        if ($stmt->rowCount() < 1) throw new InvalidArgumentException('Candidate was not found or already revoked.');
        return [
            'candidate_id' => $candidateId,
            'attempt_id' => $attemptId,
            'status' => 'revoked',
            'domain_mutations_performed' => false,
        ];
    }
}

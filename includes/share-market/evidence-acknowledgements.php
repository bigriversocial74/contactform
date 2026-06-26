<?php
declare(strict_types=1);

require_once __DIR__ . '/evidence-candidates.php';

if (!function_exists('mg_share_market_ack_schema_available')) {
    function mg_share_market_ack_schema_available(PDO $pdo): bool
    {
        $stmt = $pdo->query("SHOW TABLES LIKE 'share_market_evidence_acknowledgements'");
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('mg_share_market_ack_roles')) {
    function mg_share_market_ack_roles(): array
    {
        return ['operator','engineering','security','legal','product_owner','executive','other'];
    }
}

if (!function_exists('mg_share_market_ack_role')) {
    function mg_share_market_ack_role($value): string
    {
        $role = trim((string)$value);
        if (!in_array($role, mg_share_market_ack_roles(), true)) throw new InvalidArgumentException('Reviewer role is invalid.');
        return $role;
    }
}

if (!function_exists('mg_share_market_ack_note')) {
    function mg_share_market_ack_note($value, int $max = 2000): string
    {
        $note = trim((string)$value);
        if (mb_strlen($note) > $max) throw new InvalidArgumentException('Reviewer note is too long.');
        return $note;
    }
}

if (!function_exists('mg_share_market_ack_public_id')) {
    function mg_share_market_ack_public_id(): string
    {
        return mg_share_market_execution_audit_public_id();
    }
}

if (!function_exists('mg_share_market_ack_fetch_candidate')) {
    function mg_share_market_ack_fetch_candidate(PDO $pdo, array $attempt, string $candidateId, array $currentExport = []): array
    {
        $candidateId = trim($candidateId);
        if ($candidateId === '' || preg_match('/^[A-Za-z0-9-]{20,80}$/', $candidateId) !== 1) throw new InvalidArgumentException('Candidate identifier is invalid.');
        $stmt = $pdo->prepare('SELECT c.*,u.email AS created_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS created_by_name FROM share_market_evidence_candidates c LEFT JOIN users u ON u.id=c.created_by_user_id WHERE c.public_id=? AND c.execution_attempt_id=? LIMIT 1');
        $stmt->execute([$candidateId, (int)$attempt['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new InvalidArgumentException('Evidence candidate not found.');
        if ((string)($row['status'] ?? '') === 'revoked') throw new InvalidArgumentException('Revoked evidence candidates cannot be acknowledged.');
        return mg_share_market_candidate_decode($row, $currentExport);
    }
}

if (!function_exists('mg_share_market_ack_compare')) {
    function mg_share_market_ack_compare(array $ack, array $currentExport): array
    {
        $ackHash = (string)($ack['package_hash'] ?? '');
        $currentHash = (string)($currentExport['package_hash'] ?? '');
        return [
            'acknowledged_hash' => $ackHash,
            'current_hash' => $currentHash,
            'matches_current' => $ackHash !== '' && $ackHash === $currentHash,
            'drift_status' => $ackHash === '' || $currentHash === '' ? 'unknown' : ($ackHash === $currentHash ? 'matching' : 'drifted'),
        ];
    }
}

if (!function_exists('mg_share_market_ack_decode')) {
    function mg_share_market_ack_decode(array $row, array $currentExport = []): array
    {
        if ($currentExport) $row['drift'] = mg_share_market_ack_compare($row, $currentExport);
        return $row;
    }
}

if (!function_exists('mg_share_market_ack_list')) {
    function mg_share_market_ack_list(PDO $pdo, string $attemptId, array $actor = []): array
    {
        if (!mg_share_market_ack_schema_available($pdo)) throw new RuntimeException('Buy-In acknowledgement schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $currentExport = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $stmt = $pdo->prepare('SELECT a.*,c.public_id AS candidate_public_id,u.email AS created_by_email,COALESCE(NULLIF(u.display_name,\'\'),u.email) AS created_by_name FROM share_market_evidence_acknowledgements a INNER JOIN share_market_evidence_candidates c ON c.id=a.evidence_candidate_id LEFT JOIN users u ON u.id=a.created_by_user_id WHERE a.execution_attempt_id=? ORDER BY a.created_at DESC,a.id DESC');
        $stmt->execute([(int)$attempt['id']]);
        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[] = mg_share_market_ack_decode($row, $currentExport);
        return [
            'attempt_id' => $attemptId,
            'current_package_hash' => (string)($currentExport['package_hash'] ?? ''),
            'items' => $items,
            'domain_mutations_performed' => false,
        ];
    }
}

if (!function_exists('mg_share_market_ack_record')) {
    function mg_share_market_ack_record(PDO $pdo, string $attemptId, array $actor, array $input = []): array
    {
        if (!mg_share_market_ack_schema_available($pdo)) throw new RuntimeException('Buy-In acknowledgement schema is not installed.');
        $attempt = mg_share_market_candidate_fetch_attempt($pdo, $attemptId);
        $currentExport = mg_share_market_evidence_export($pdo, $attemptId, $actor);
        $candidate = mg_share_market_ack_fetch_candidate($pdo, $attempt, (string)($input['candidate_id'] ?? ''), $currentExport);
        $packageHash = (string)($candidate['package_hash'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/', $packageHash)) throw new RuntimeException('Candidate package hash is invalid.');
        $role = mg_share_market_ack_role($input['reviewer_role'] ?? 'operator');
        $note = mg_share_market_ack_note($input['reviewer_note'] ?? '');
        $stmt = $pdo->prepare('INSERT INTO share_market_evidence_acknowledgements (public_id,execution_attempt_id,approval_request_id,evidence_candidate_id,package_hash,reviewer_role,acknowledgement_status,reviewer_note,created_by_user_id) VALUES (?,?,?,?,?,?,?,?,?)');
        $publicId = mg_share_market_ack_public_id();
        $stmt->execute([
            $publicId,
            (int)$attempt['id'],
            (int)$attempt['approval_request_id'],
            (int)$candidate['id'],
            $packageHash,
            $role,
            'acknowledged',
            $note !== '' ? $note : null,
            (int)($actor['id'] ?? 0),
        ]);
        return [
            'acknowledgement_id' => $publicId,
            'attempt_id' => $attemptId,
            'candidate_id' => (string)$candidate['public_id'],
            'package_hash' => $packageHash,
            'reviewer_role' => $role,
            'drift' => mg_share_market_ack_compare(['package_hash' => $packageHash], $currentExport),
            'domain_mutations_performed' => false,
        ];
    }
}

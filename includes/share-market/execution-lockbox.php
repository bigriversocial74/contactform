<?php
declare(strict_types=1);

require_once __DIR__ . '/launch-readiness.php';

if (!function_exists('mg_share_market_lockbox_packet_hash')) {
    function mg_share_market_lockbox_packet_hash(array $packet): string
    {
        return hash('sha256', json_encode(mg_share_market_admin_canonicalize($packet), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}

if (!function_exists('mg_share_market_lockbox_evidence_packet')) {
    function mg_share_market_lockbox_evidence_packet(PDO $pdo, int $participantUserId): array
    {
        $readiness = mg_share_market_launch_readiness_for_user($pdo, $participantUserId);
        $series = is_array($readiness['series'] ?? null) ? $readiness['series'] : [];
        $reserve = is_array($readiness['reserve'] ?? null) ? $readiness['reserve'] : [];
        $treasury = is_array($readiness['treasury'] ?? null) ? $readiness['treasury'] : [];
        $packet = [
            'packet_type' => 'dave_execution_readiness_lockbox',
            'packet_version' => 1,
            'participant_user_id' => $participantUserId,
            'series_public_id' => (string)($series['public_id'] ?? $series['series_id'] ?? ''),
            'series_name' => (string)($series['name'] ?? 'Controlled DAVE series'),
            'status' => !empty($readiness['complete']) ? 'lockbox_ready' : 'lockbox_blocked',
            'readiness_score' => (int)($readiness['score'] ?? 0),
            'launch_readiness' => $readiness,
            'approved_reserve' => $reserve,
            'treasury' => $treasury,
            'required_before_execution' => [
                'legal_operator_checklist' => 'placeholder_required_before_any_execution',
                'final_admin_packet_review' => 'required',
                'separate_execution_pr' => 'required',
                'maker_checker_signoff' => 'required',
            ],
            'hard_locks' => [
                'public_launch' => false,
                'payment_execution' => false,
                'resale' => false,
                'live_issuance' => false,
                'ledger_execution' => false,
            ],
            'created_at' => gmdate('c'),
            'execution_enabled' => false,
        ];
        $packet['packet_hash'] = mg_share_market_lockbox_packet_hash($packet);
        return $packet;
    }
}

if (!function_exists('mg_share_market_lockbox_admin_queue')) {
    function mg_share_market_lockbox_admin_queue(PDO $pdo): array
    {
        $launchQueue = mg_share_market_launch_readiness_admin_queue($pdo);
        $items = [];
        foreach (($launchQueue['items'] ?? []) as $item) {
            $participantUserId = (int)($item['participant_user_id'] ?? 0);
            if ($participantUserId < 1) continue;
            $packet = mg_share_market_lockbox_evidence_packet($pdo, $participantUserId);
            $items[] = [
                'participant_user_id' => $participantUserId,
                'merchant_label' => (string)($item['merchant_label'] ?? 'Merchant'),
                'series_name' => (string)($packet['series_name'] ?? 'Controlled DAVE series'),
                'series_public_id' => (string)($packet['series_public_id'] ?? ''),
                'status' => (string)$packet['status'],
                'packet_hash' => (string)$packet['packet_hash'],
                'readiness_score' => (int)$packet['readiness_score'],
                'packet' => $packet,
                'execution_enabled' => false,
            ];
        }
        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'ready' => count(array_filter($items, static fn(array $i): bool => (string)$i['status'] === 'lockbox_ready')),
                'blocked' => count(array_filter($items, static fn(array $i): bool => (string)$i['status'] !== 'lockbox_ready')),
                'execution_locked' => count($items),
            ],
            'execution_enabled' => false,
        ];
    }
}

if (!function_exists('mg_share_market_lockbox_for_user')) {
    function mg_share_market_lockbox_for_user(PDO $pdo, int $participantUserId): array
    {
        $packet = mg_share_market_lockbox_evidence_packet($pdo, $participantUserId);
        return ['packet'=>$packet,'status'=>(string)$packet['status'],'next_action'=>((string)$packet['status'] === 'lockbox_ready') ? 'Ready for future execution review. Launch remains locked.' : 'Resolve launch-readiness blockers before the lockbox can be ready.','execution_enabled'=>false];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/readiness-summary.php';

if (!function_exists('mg_share_market_admin_readiness_bucket')) {
    function mg_share_market_admin_readiness_bucket(array $summary): string
    {
        $action = (string)($summary['next_action']['type'] ?? 'locked');
        $label = strtolower((string)($summary['next_action']['label'] ?? ''));
        if ($action === 'attention') return 'blocked';
        if (str_contains($label, 'request review') || str_contains($label, 'create series') || str_contains($label, 'submit series') || str_contains($label, 'reserve')) return 'needs_merchant_action';
        if (str_contains($label, 'operator review')) return 'ready_for_operator_review';
        if (($summary['current_workflow'] ?? '') === 'series_submitted' || str_contains($label, 'admin review')) return 'needs_admin_review';
        if (($summary['next_action']['type'] ?? '') === 'ready') return 'operator_complete';
        return 'blocked';
    }
}

if (!function_exists('mg_share_market_admin_readiness_next_action')) {
    function mg_share_market_admin_readiness_next_action(array $summary, string $bucket): array
    {
        return match ($bucket) {
            'needs_merchant_action' => ['label'=>'Merchant action required','href'=>'/account-share-market-approvals.php','type'=>'merchant'],
            'needs_admin_review' => ['label'=>'Open DAVE Review Console','href'=>'/account-share-market-approvals.php','type'=>'admin'],
            'ready_for_operator_review' => ['label'=>'Open DAVE Lockbox','href'=>'/account-share-market-lockbox.php','type'=>'admin'],
            'operator_complete' => ['label'=>'Ready for future execution review — launch locked','href'=>'/account-share-market-lockbox.php','type'=>'ready'],
            default => ['label'=>'Review blockers','href'=>'/account-share-market-approvals.php','type'=>'blocked'],
        };
    }
}

if (!function_exists('mg_share_market_admin_readiness_dashboard')) {
    function mg_share_market_admin_readiness_dashboard(PDO $pdo): array
    {
        $items = [];
        try {
            $stmt = $pdo->query("SELECT DISTINCT e.participant_user_id,u.email,u.display_name,u.full_name,e.public_name,e.legal_name FROM share_market_enrollments e LEFT JOIN users u ON u.id=e.participant_user_id ORDER BY e.updated_at DESC,e.id DESC LIMIT 250");
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) {
                $userId = (int)($row['participant_user_id'] ?? 0);
                if ($userId < 1) continue;
                $summary = mg_share_market_readiness_summary($pdo, $userId);
                $bucket = mg_share_market_admin_readiness_bucket($summary);
                $items[] = [
                    'participant_user_id' => $userId,
                    'merchant_label' => trim((string)($row['public_name'] ?? $row['legal_name'] ?? $row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? 'Merchant')),
                    'bucket' => $bucket,
                    'next_action' => mg_share_market_admin_readiness_next_action($summary, $bucket),
                    'summary' => $summary,
                    'execution_enabled' => false,
                ];
            }
        } catch (Throwable) { $items = []; }
        $counts = ['total'=>count($items),'needs_merchant_action'=>0,'needs_admin_review'=>0,'ready_for_operator_review'=>0,'operator_complete'=>0,'blocked'=>0,'execution_locked'=>count($items)];
        foreach ($items as $item) {
            $bucket = (string)($item['bucket'] ?? 'blocked');
            if (isset($counts[$bucket])) $counts[$bucket]++;
        }
        return ['items'=>$items,'summary'=>$counts,'filters'=>['all','needs_merchant_action','needs_admin_review','ready_for_operator_review','operator_complete','blocked'],'execution_enabled'=>false];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-readiness-dashboard.php';
require_once __DIR__ . '/notifications.php';

if (!function_exists('mg_share_market_digest_event_key')) {
    function mg_share_market_digest_event_key(string $audience, int $userId, string $kind, string $fingerprint): string
    {
        $safeKind = strtolower(preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $kind));
        return 'readiness_digest.' . $audience . '.' . $userId . '.' . $safeKind . '.' . substr(hash('sha256', $fingerprint), 0, 16);
    }
}

if (!function_exists('mg_share_market_digest_item_kind')) {
    function mg_share_market_digest_item_kind(array $item): string
    {
        $bucket = (string)($item['bucket'] ?? 'blocked');
        return match ($bucket) {
            'needs_merchant_action' => 'merchant_action_needed',
            'needs_admin_review' => 'admin_review_needed',
            'ready_for_operator_review' => 'operator_review_ready',
            'operator_complete' => 'operator_checklist_complete',
            default => 'blockers_detected',
        };
    }
}

if (!function_exists('mg_share_market_digest_item_title')) {
    function mg_share_market_digest_item_title(string $kind, string $merchant): string
    {
        return match ($kind) {
            'merchant_action_needed' => 'DAVE merchant action needed',
            'admin_review_needed' => 'DAVE admin review needed',
            'operator_review_ready' => 'DAVE series ready for operator review',
            'operator_checklist_complete' => 'DAVE operator checklist complete',
            default => 'DAVE readiness blocker detected',
        };
    }
}

if (!function_exists('mg_share_market_digest_item_body')) {
    function mg_share_market_digest_item_body(string $kind, array $item): string
    {
        $merchant = (string)($item['merchant_label'] ?? 'Merchant');
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
        $headline = (string)($summary['headline'] ?? 'Review readiness status.');
        return match ($kind) {
            'merchant_action_needed' => $merchant . ' needs to complete the next DAVE readiness step: ' . $headline,
            'admin_review_needed' => $merchant . ' needs admin review for DAVE readiness: ' . $headline,
            'operator_review_ready' => $merchant . ' is ready for operator review. Launch remains locked.',
            'operator_checklist_complete' => $merchant . ' completed operator review. Future execution review is still locked.',
            default => $merchant . ' has a DAVE readiness blocker: ' . $headline,
        };
    }
}

if (!function_exists('mg_share_market_send_admin_digest_for_item')) {
    function mg_share_market_send_admin_digest_for_item(PDO $pdo, array $item): void
    {
        $participantUserId = (int)($item['participant_user_id'] ?? 0);
        if ($participantUserId < 1) return;
        $kind = mg_share_market_digest_item_kind($item);
        $merchant = (string)($item['merchant_label'] ?? 'Merchant');
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
        $fingerprint = $kind . '|' . $participantUserId . '|' . (string)($summary['current_workflow'] ?? '') . '|' . (string)($summary['headline'] ?? '') . '|' . (string)($summary['progress_percent'] ?? '0');
        mg_share_market_notify_admins(
            $pdo,
            $participantUserId,
            mg_share_market_digest_item_title($kind, $merchant),
            mg_share_market_digest_item_body($kind, $item),
            mg_share_market_digest_event_key('admin', $participantUserId, $kind, $fingerprint),
            ['target_type'=>'readiness_digest','target_public_id'=>'user_' . $participantUserId,'share_market_status'=>$kind,'readiness_bucket'=>(string)($item['bucket'] ?? 'blocked')]
        );
    }
}

if (!function_exists('mg_share_market_send_merchant_digest_for_item')) {
    function mg_share_market_send_merchant_digest_for_item(PDO $pdo, array $item): void
    {
        $participantUserId = (int)($item['participant_user_id'] ?? 0);
        if ($participantUserId < 1) return;
        $summary = is_array($item['summary'] ?? null) ? $item['summary'] : [];
        $bucket = (string)($item['bucket'] ?? 'blocked');
        $headline = (string)($summary['headline'] ?? 'Review DAVE readiness status.');
        $message = $headline;
        if ($bucket === 'ready_for_operator_review') $message = 'Operator review is in progress. Launch remains locked.';
        if ($bucket === 'operator_complete') $message = 'Operator checklist complete — launch still locked.';
        if ($bucket === 'blocked') $message = 'A readiness blocker needs review: ' . $headline;
        $fingerprint = 'merchant|' . $participantUserId . '|' . $bucket . '|' . $headline . '|' . (string)($summary['progress_percent'] ?? '0');
        mg_share_market_notify_merchant(
            $pdo,
            $participantUserId,
            0,
            'DAVE readiness status updated',
            $message,
            mg_share_market_digest_event_key('merchant', $participantUserId, $bucket, $fingerprint),
            ['target_type'=>'readiness_digest','target_public_id'=>'user_' . $participantUserId,'share_market_status'=>$bucket]
        );
    }
}

if (!function_exists('mg_share_market_send_readiness_digest')) {
    function mg_share_market_send_readiness_digest(PDO $pdo): array
    {
        $dashboard = mg_share_market_admin_readiness_dashboard($pdo);
        $adminSent = 0;
        $merchantSent = 0;
        foreach (($dashboard['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            mg_share_market_send_admin_digest_for_item($pdo, $item);
            $adminSent++;
            mg_share_market_send_merchant_digest_for_item($pdo, $item);
            $merchantSent++;
        }
        return ['admin_digest_items'=>$adminSent,'merchant_digest_items'=>$merchantSent,'summary'=>$dashboard['summary'] ?? [],'execution_enabled'=>false];
    }
}

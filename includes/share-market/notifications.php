<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/communications/_communications.php';

if (!function_exists('mg_share_market_notification_safe')) {
    function mg_share_market_notification_safe(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'share_market.notification_failed', 'Share Market notification was not created.', ['exception_class' => $e::class]);
            }
        }
    }
}

if (!function_exists('mg_share_market_notification_admin_ids')) {
    function mg_share_market_notification_admin_ids(PDO $pdo, int $excludeUserId = 0): array
    {
        try {
            $stmt = $pdo->query(
                "SELECT DISTINCT u.id
                 FROM users u
                 INNER JOIN user_roles ur ON ur.user_id=u.id
                 INNER JOIN roles r ON r.id=ur.role_id
                 LEFT JOIN role_permissions rp ON rp.role_id=r.id
                 LEFT JOIN permissions p ON p.id=rp.permission_id
                 WHERE u.status='active' AND (r.slug='super_admin' OR p.slug='share_market.admin')
                 ORDER BY u.id ASC LIMIT 50"
            );
            $ids = [];
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : []) ?: [] as $id) {
                $id = (int)$id;
                if ($id > 0 && $id !== $excludeUserId) $ids[$id] = $id;
            }
            return array_values($ids);
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('mg_share_market_notify_admins')) {
    function mg_share_market_notify_admins(PDO $pdo, int $actorUserId, string $title, string $body, string $eventKey, array $context = []): void
    {
        mg_share_market_notification_safe(function () use ($pdo, $actorUserId, $title, $body, $eventKey, $context): void {
            foreach (mg_share_market_notification_admin_ids($pdo, $actorUserId) as $adminId) {
                mg_create_notification($pdo, $adminId, 'share_market', $title, $body, '/account-share-market-approvals.php', array_merge($context, ['actor_user_id'=>$actorUserId, 'event_key'=>'share_market.admin.' . $adminId . '.' . $eventKey]));
            }
        });
    }
}

if (!function_exists('mg_share_market_notify_merchant')) {
    function mg_share_market_notify_merchant(PDO $pdo, int $merchantUserId, int $actorUserId, string $title, string $body, string $eventKey, array $context = []): void
    {
        mg_share_market_notification_safe(function () use ($pdo, $merchantUserId, $actorUserId, $title, $body, $eventKey, $context): void {
            mg_create_notification($pdo, $merchantUserId, 'share_market', $title, $body, '/account-market-shares.php', array_merge($context, ['actor_user_id'=>$actorUserId, 'event_key'=>'share_market.merchant.' . $merchantUserId . '.' . $eventKey]));
        });
    }
}

if (!function_exists('mg_share_market_notify_enrollment_submitted')) {
    function mg_share_market_notify_enrollment_submitted(PDO $pdo, int $actorUserId, array $snapshot): void
    {
        $enrollment = is_array($snapshot['enrollment'] ?? null) ? $snapshot['enrollment'] : [];
        $name = (string)($enrollment['public_name'] ?? $enrollment['legal_name'] ?? 'A merchant');
        $id = (string)($enrollment['public_id'] ?? $enrollment['participant_id'] ?? ('user_' . $actorUserId));
        mg_share_market_notify_admins($pdo, $actorUserId, 'DAVE Share Market review requested', $name . ' submitted a merchant opt-in request for admin review.', 'enrollment_submitted.' . strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $id)), ['target_type'=>'enrollment','target_public_id'=>$id,'share_market_status'=>'under_review']);
    }
}

if (!function_exists('mg_share_market_notify_series_submitted')) {
    function mg_share_market_notify_series_submitted(PDO $pdo, int $actorUserId, array $result): void
    {
        $series = is_array($result['series'] ?? null) ? $result['series'] : [];
        $name = (string)($series['name'] ?? 'A controlled market series');
        $id = (string)($series['series_id'] ?? $series['public_id'] ?? ('user_' . $actorUserId));
        mg_share_market_notify_admins($pdo, $actorUserId, 'DAVE series submitted for review', $name . ' was submitted for Share Market admin review.', 'series_submitted.' . strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $id)), ['target_type'=>'series','target_public_id'=>$id,'share_market_status'=>'submitted']);
    }
}

if (!function_exists('mg_share_market_notify_decision')) {
    function mg_share_market_notify_decision(PDO $pdo, int $actorUserId, array $input): void
    {
        $targetType = strtolower(trim((string)($input['target_type'] ?? '')));
        $decision = strtolower(trim((string)($input['decision'] ?? '')));
        $note = trim((string)($input['note'] ?? ''));
        if ($targetType === 'enrollment') {
            $participantId = trim((string)($input['participant_id'] ?? ''));
            if ($participantId === '') return;
            $stmt = $pdo->prepare('SELECT participant_user_id,public_name,legal_name,status FROM share_market_enrollments WHERE public_id=? LIMIT 1');
            $stmt->execute([$participantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $merchantId = (int)($row['participant_user_id'] ?? 0);
            if ($merchantId < 1) return;
            $state = (string)($row['status'] ?? $decision);
            $title = 'DAVE Share Market review updated';
            if ($state === 'approved') $title = 'DAVE Share Market opt-in approved';
            if ($state === 'rejected') $title = 'DAVE Share Market opt-in rejected';
            if (in_array($state, ['paused','suspended'], true)) $title = 'DAVE Share Market opt-in ' . $state;
            mg_share_market_notify_merchant($pdo, $merchantId, $actorUserId, $title, $note !== '' ? $note : 'Your merchant opt-in review status was updated.', 'enrollment_decision.' . $participantId . '.' . $state, ['target_type'=>'enrollment','target_public_id'=>$participantId,'share_market_status'=>$state]);
            return;
        }
        if ($targetType === 'series') {
            $seriesId = trim((string)($input['series_id'] ?? ''));
            if ($seriesId === '') return;
            $stmt = $pdo->prepare('SELECT participant_user_id,name,state FROM share_market_series WHERE public_id=? LIMIT 1');
            $stmt->execute([$seriesId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $merchantId = (int)($row['participant_user_id'] ?? 0);
            if ($merchantId < 1) return;
            $state = (string)($row['state'] ?? $decision);
            $seriesName = (string)($row['name'] ?? 'Your DAVE series');
            $title = 'DAVE series review updated';
            if ($state === 'approved') $title = 'DAVE series approved';
            if ($state === 'rejected') $title = 'DAVE series rejected';
            if ($state === 'changes_requested') $title = 'DAVE series changes requested';
            if ($state === 'paused') $title = 'DAVE series paused';
            $body = $note !== '' ? $note : $seriesName . ' review status was updated.';
            mg_share_market_notify_merchant($pdo, $merchantId, $actorUserId, $title, $body, 'series_decision.' . $seriesId . '.' . $state, ['target_type'=>'series','target_public_id'=>$seriesId,'share_market_status'=>$state,'series_name'=>$seriesName]);
        }
    }
}

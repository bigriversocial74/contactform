<?php
declare(strict_types=1);

require_once __DIR__ . '/readiness-notification-digest.php';

if (!function_exists('mg_share_market_health_item')) {
    function mg_share_market_health_item(string $key, string $label, string $status, string $issue = '', string $fix = ''): array
    {
        return ['key'=>$key,'label'=>$label,'status'=>$status,'issue'=>$issue,'recommended_fix'=>$fix];
    }
}

if (!function_exists('mg_share_market_health_file')) {
    function mg_share_market_health_file(string $key, string $label, string $relativePath): array
    {
        $path = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        if (is_file($path)) return mg_share_market_health_item($key, $label, 'healthy', 'Found ' . $relativePath, 'No action needed.');
        return mg_share_market_health_item($key, $label, 'blocked', 'Missing ' . $relativePath, 'Restore or deploy the missing file.');
    }
}

if (!function_exists('mg_share_market_health_function')) {
    function mg_share_market_health_function(string $key, string $label, string $function, string $fix): array
    {
        if (function_exists($function)) return mg_share_market_health_item($key, $label, 'healthy', $function . ' is loaded.', 'No action needed.');
        return mg_share_market_health_item($key, $label, 'blocked', $function . ' is not loaded.', $fix);
    }
}

if (!function_exists('mg_share_market_health_table')) {
    function mg_share_market_health_table(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return mg_share_market_health_item('table_' . $table, $table, 'healthy', $table . ' is queryable.', 'No action needed.');
        } catch (Throwable $e) {
            return mg_share_market_health_item('table_' . $table, $table, 'blocked', $table . ' is not queryable.', 'Run the Share Market database migrations and verify privileges.');
        }
    }
}

if (!function_exists('mg_share_market_readiness_health_audit')) {
    function mg_share_market_readiness_health_audit(PDO $pdo, array $user = []): array
    {
        $items = [];
        $files = [
            ['merchant_summary_api','Merchant readiness summary API','api/share-market/readiness-summary.php'],
            ['admin_dashboard_api','Admin readiness dashboard API','api/admin/share-market/readiness-dashboard.php'],
            ['readiness_digest_api','Readiness digest API','api/admin/share-market/readiness-digest.php'],
            ['lockbox_api','Execution lockbox API','api/share-market/execution-lockbox.php'],
            ['admin_lockbox_api','Admin lockbox API','api/admin/share-market/lockbox.php'],
            ['operator_api','Operator checklist API','api/admin/share-market/operator-checklist.php'],
            ['launch_api','Launch readiness API','api/share-market/launch-readiness.php'],
            ['credit_reserve_api','Credit reserve API','api/share-market/credit-reserve.php'],
            ['merchant_summary_js','Merchant readiness summary JS','assets/js/dave-share-market-readiness-summary.js'],
            ['admin_dashboard_js','Admin readiness dashboard JS','assets/js/share-market-readiness-dashboard-admin.js'],
            ['digest_js','Readiness digest JS','assets/js/share-market-readiness-digest-admin.js'],
        ];
        foreach ($files as $file) $items[] = mg_share_market_health_file($file[0], $file[1], $file[2]);

        $functions = [
            ['merchant_summary_fn','Merchant readiness summary helper','mg_share_market_readiness_summary','Check includes/share-market/readiness-summary.php and dependencies.'],
            ['admin_dashboard_fn','Admin readiness dashboard helper','mg_share_market_admin_readiness_dashboard','Check includes/share-market/admin-readiness-dashboard.php and dependencies.'],
            ['digest_fn','Readiness digest helper','mg_share_market_send_readiness_digest','Check includes/share-market/readiness-notification-digest.php and notification helpers.'],
            ['lockbox_fn','Lockbox helper','mg_share_market_lockbox_for_user','Check includes/share-market/execution-lockbox.php.'],
            ['operator_fn','Operator checklist helper','mg_share_market_operator_checklist_status','Check includes/share-market/operator-checklist.php.'],
            ['launch_fn','Launch readiness helper','mg_share_market_launch_readiness_for_user','Check includes/share-market/launch-readiness.php.'],
            ['credit_reserve_fn','Credit reserve helper','mg_share_market_credit_reserve_user_snapshot','Check includes/share-market/credit-reserve.php.'],
            ['notify_admin_fn','Admin notification helper','mg_share_market_notify_admins','Check includes/share-market/notifications.php and api/communications/_communications.php.'],
            ['notify_merchant_fn','Merchant notification helper','mg_share_market_notify_merchant','Check includes/share-market/notifications.php and api/communications/_communications.php.'],
        ];
        foreach ($functions as $fn) $items[] = mg_share_market_health_function($fn[0], $fn[1], $fn[2], $fn[3]);

        if (function_exists('mg_share_market_sql_schema_available') && mg_share_market_sql_schema_available($pdo)) {
            $items[] = mg_share_market_health_item('schema_available', 'Share Market SQL schema adapter', 'healthy', 'Schema adapter reports available.', 'No action needed.');
        } else {
            $items[] = mg_share_market_health_item('schema_available', 'Share Market SQL schema adapter', 'blocked', 'Schema adapter reports unavailable.', 'Run Share Market migrations and confirm database permissions.');
        }

        foreach (['share_market_enrollments','share_market_series','share_market_approval_requests','share_market_credit_treasuries','notifications'] as $table) {
            $items[] = mg_share_market_health_table($pdo, $table);
        }

        if ($user && function_exists('mg_share_market_admin_authorized') && mg_share_market_admin_authorized($user)) {
            $items[] = mg_share_market_health_item('admin_permission', 'Admin permission', 'healthy', 'Current user can access Share Market admin tools.', 'No action needed.');
        } else {
            $items[] = mg_share_market_health_item('admin_permission', 'Admin permission', 'warning', 'Unable to confirm current user has Share Market admin permission in this context.', 'Verify the user has share_market.admin or super admin permissions.');
        }

        $statusRank = ['healthy'=>0,'warning'=>1,'blocked'=>2];
        $overall = 'healthy';
        $counts = ['healthy'=>0,'warning'=>0,'blocked'=>0];
        foreach ($items as $item) {
            $status = (string)($item['status'] ?? 'warning');
            if (isset($counts[$status])) $counts[$status]++;
            if (($statusRank[$status] ?? 1) > ($statusRank[$overall] ?? 0)) $overall = $status;
        }
        return ['status'=>$overall,'counts'=>$counts,'items'=>$items,'execution_enabled'=>false,'checked_at'=>gmdate('c')];
    }
}

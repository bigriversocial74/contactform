<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$read = static function (string $path) use ($root,&$errors): string {
    $full = $root . '/' . $path;
    if (!is_file($full)) {
        $errors[] = 'Missing file: ' . $path;
        return '';
    }
    $content = file_get_contents($full);
    if (!is_string($content)) {
        $errors[] = 'Unreadable file: ' . $path;
        return '';
    }
    return $content;
};
$requireMarkers = static function (string $path, array $markers) use ($read,&$errors): void {
    $content = $read($path);
    foreach ($markers as $label => $marker) {
        if (!str_contains($content,$marker)) $errors[] = $path . ' missing ' . $label . ': ' . $marker;
    }
};

$requireMarkers('database/stage_18al_personal_agent_followup_recovery_v1.sql',[
    'preference table'=>'CREATE TABLE IF NOT EXISTS personal_agent_recovery_preferences',
    'follow-up table'=>'CREATE TABLE IF NOT EXISTS personal_agent_opportunity_followups',
    'frequency cap'=>'max_notifications_per_week',
    'quiet hours'=>'quiet_hours_start',
    'conversion timestamp'=>'converted_at',
    'migration registration'=>'stage_18al_personal_agent_followup_recovery_v1',
]);
$requireMarkers('config/migrations.php',['canonical migration'=>'stage_18al_personal_agent_followup_recovery_v1.sql']);
$requireMarkers('includes/personal-agent/opportunity-recovery.php',[
    'schema guard'=>'mg_personal_agent_recovery_schema_ready',
    'customer preferences'=>'mg_personal_agent_recovery_update_preferences',
    'event automation'=>'mg_personal_agent_recovery_on_event',
    'cart follow-up'=>'cart_abandoned',
    'checkout follow-up'=>'checkout_abandoned',
    'campaign expiry'=>'campaign_expiring',
    'unavailable alternative'=>'unavailable_alternative',
    'frequency cap'=>'max_notifications_per_week',
    'cooldown'=>'cooldown_hours',
    'quiet hours'=>'mg_personal_agent_recovery_quiet_release',
    'notification pipeline'=>'mg_create_notification',
    'worker scan'=>'mg_personal_agent_recovery_scan',
    'worker delivery'=>'mg_personal_agent_recovery_process_due',
    'aggregate merchant reporting'=>'mg_personal_agent_recovery_merchant_analytics',
]);
$requireMarkers('includes/personal-agent/opportunity-attribution.php',[
    'follow-up events'=>'followup_delivered',
    'conversion events'=>'recovery_converted',
    'recovery hook'=>'mg_personal_agent_recovery_on_event',
]);
$requireMarkers('includes/personal-agent/recovery-response.php',[
    'saved intent'=>'mg_personal_agent_recovery_intent_items',
    'deterministic response'=>'mg_personal_agent_recovery_start_response',
    'manual reminder'=>'mg_personal_agent_recovery_parse_remind_at',
    'no model usage'=>'model_key\'=>\'deterministic_recovery',
]);
$requireMarkers('includes/personal-agent/credit-response.php',[
    'deterministic credit bypass'=>'$deterministicRecovery',
    'recovery chat wrapper'=>'mg_personal_agent_chat_with_recovery_response',
]);
$requireMarkers('api/user-agent/opportunity-recovery.php',[
    'account auth'=>'mg_require_api_user',
    'write CSRF'=>'mg_require_csrf_for_write',
    'preference action'=>'$action === \'preferences\'',
    'schedule action'=>'$action === \'schedule\'',
    'snooze action'=>"'snooze','dismiss','mute','resume'",
]);
$requireMarkers('api/communications/personal-agent-recovery-worker.php',[
    'admin permission'=>"mg_require_permission('admin.users.view')",
    'scan'=>'mg_personal_agent_recovery_scan',
    'delivery'=>'mg_personal_agent_recovery_process_due',
]);
$requireMarkers('scripts/process_personal_agent_opportunity_recovery.php',[
    'CLI only'=>"PHP_SAPI!=='cli'",
    'scan'=>'mg_personal_agent_recovery_scan',
    'delivery'=>'mg_personal_agent_recovery_process_due',
]);
$requireMarkers('includes/personal-agent/workspace-dashboard.php',[
    'recovery list'=>'data-personal-agent-recovery-list',
    'preferences form'=>'data-personal-agent-recovery-settings',
    'privacy boundary'=>'aggregate outcomes only',
]);
$requireMarkers('assets/js/personal-agent-recovery.js',[
    'recovery endpoint'=>'/api/user-agent/opportunity-recovery.php',
    'snooze'=>'data-recovery-action',
    'preferences'=>'action:\'preferences\'',
]);
$requireMarkers('assets/js/saved-opportunities.js',[
    'remind button'=>'data-saved-opportunity-remind',
    'recovery endpoint'=>'/api/user-agent/opportunity-recovery.php',
]);
$requireMarkers('api/merchant/personal-agent-attribution.php',['aggregate recovery'=>'mg_personal_agent_recovery_merchant_analytics']);
$requireMarkers('includes/merchant-agent-roi-view.php',[
    'recovery reporting'=>'Saved Opportunity Recovery',
    'privacy statement'=>'do not receive private Agent messages',
    'recovered revenue'=>'data-recovery-revenue',
]);
$requireMarkers('assets/js/merchant-agent-roi.js',[
    'recovery renderer'=>'renderRecovery',
    'recovered purchases'=>'recovered_purchases',
    'recovered revenue'=>'recovered_revenue_cents',
]);
$requireMarkers('docs/personal-agent-followup-recovery-v1.md',[
    'cron command'=>'process_personal_agent_opportunity_recovery.php 100',
    'privacy boundary'=>'Privacy boundary',
]);

if ($errors !== []) {
    fwrite(STDERR,"Personal Agent Follow-Up Recovery v1 validation failed:\n- " . implode("\n- ",$errors) . "\n");
    exit(1);
}

echo "Personal Agent Follow-Up Recovery v1 contract passed.\n";

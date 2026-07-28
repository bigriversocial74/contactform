<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    if (!is_file($path)) throw new RuntimeException('Missing required file: ' . $relative);
    $content = file_get_contents($path);
    if (!is_string($content) || trim($content) === '') throw new RuntimeException('Empty required file: ' . $relative);
    return $content;
};

try {
    $reporting = $read('includes/merchant-crm-reporting.php');
    $reportingApi = $read('api/merchant/crm-reporting.php');
    $analytics = $read('assets/js/merchant-crm-reporting-v1.js');
    $directory = $read('assets/js/merchant-crm-advanced-filters-v1.js');
    $directoryCss = $read('assets/css/merchant-crm-advanced-filters-v1.css');
    $action = $read('assets/js/merchant-crm-make-good-send-v1.js');
    $actionCss = $read('assets/css/merchant-crm-make-good-send-v1.css');
    $campaignsApi = $read('api/merchant/crm-reward-campaigns.php');
    $sendApi = $read('api/merchant/crm-campaign-send.php');
    $inviteApi = $read('api/merchant/crm-send-reward-invite.php');
    $sendService = $read('includes/merchant-crm-campaign-send-service.php');
    $page = $read('merchant-crm.php');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

$checks = [
    'Merchant CRM loads the new reporting filtering and make-good layers' =>
        str_contains($page, 'merchant-crm-reporting-v1.js')
        && str_contains($page, 'merchant-crm-advanced-filters-v1.js')
        && str_contains($page, 'merchant-crm-make-good-send-v1.js')
        && str_contains($page, 'merchant-crm-advanced-filters-v1.css')
        && str_contains($page, 'merchant-crm-make-good-send-v1.css')
        && !str_contains($page, 'merchant-crm-contact-action-modal.js'),
    'reporting endpoint is authenticated read-only merchant scope' =>
        str_contains($reportingApi, "mg_require_method('GET')")
        && str_contains($reportingApi, "mg_require_permission('merchant.campaigns.view')")
        && str_contains($reportingApi, 'mg_merchant_ensure_workspace($pdo, $user)')
        && !str_contains($reportingApi, 'INSERT INTO')
        && !str_contains($reportingApi, 'UPDATE '),
    'reporting supports exact 7 30 and 90 day windows' =>
        str_contains($reporting, 'MG_MERCHANT_CRM_REPORTING_WINDOWS = [7, 30, 90]')
        && str_contains($analytics, '/api/merchant/crm-reporting.php?days=')
        && str_contains($analytics, "range.addEventListener('change'"),
    'reporting uses canonical contacts and owner-scoped lifecycle sources' =>
        str_contains($reporting, 'mg_merchant_crm_reporting_contacts')
        && str_contains($reporting, 'mc.merchant_user_id=?')
        && str_contains($reporting, 'merged_into_contact_id IS NULL')
        && str_contains($reporting, 'message_threads')
        && str_contains($reporting, 'wallet_items')
        && str_contains($reporting, "event_type='crm.followup.created'"),
    'dashboard metrics are server rendered with explicit definitions' =>
        str_contains($analytics, 'function render(report)')
        && str_contains($analytics, 'definitions.high_intent')
        && str_contains($analytics, 'definitions.needs_followup')
        && str_contains($analytics, 'trends.claims_redeems')
        && str_contains($analytics, 'trends.messages'),
    'reporting keeps a fail-soft local fallback' =>
        str_contains($analytics, 'function fallback(days)')
        && str_contains($analytics, "hero.dataset.reportingState = 'fallback'"),
    'filter button opens a real lifecycle filter panel' =>
        str_contains($directory, 'data-crm-advanced-filters')
        && str_contains($directory, 'Lifecycle stage')
        && str_contains($directory, 'CRM status')
        && str_contains($directory, 'Account linked')
        && str_contains($directory, 'Any verification')
        && str_contains($directory, 'function matches'),
    'filter state is URL preserved and pagination aware' =>
        str_contains($directory, 'url.searchParams.set(key')
        && str_contains($directory, 'state.limit = 25')
        && str_contains($directory, 'state.limit += 25')
        && str_contains($directory, 'mg:crm-directory:filtered')
        && str_contains($directoryCss, '.mg-crm-advanced-filters'),
    'make-good sender is one focused campaign and message workflow' =>
        str_contains($action, '/api/merchant/crm-reward-campaigns.php?type=customer_refund')
        && str_contains($action, "required_campaign_type: 'customer_refund'")
        && str_contains($action, 'data-crm-action-message')
        && str_contains($action, 'Send customer refund / make good')
        && str_contains($action, 'Send make good'),
    'make-good sender removes unrelated tabs filters and duplicate override' =>
        !str_contains($action, 'data-crm-action-tab')
        && !str_contains($action, 'referral_reward')
        && !str_contains($action, 'data-crm-action-filter')
        && !str_contains($action, 'data-crm-action-allow-duplicate')
        && !str_contains($action, 'data-crm-action-reason'),
    'make-good sender preserves direct wallet and account invite delivery' =>
        str_contains($action, '/api/merchant/crm-campaign-send.php')
        && str_contains($action, '/api/merchant/crm-send-reward-invite.php')
        && str_contains($action, "campaign_id: campaign.id")
        && str_contains($action, '/api/merchant/crm-message.php')
        && str_contains($action, 'contact.has_account'),
    'campaign selector labels the canonical type consistently' =>
        str_contains($campaignsApi, "'customer_refund' => 'Customer Refund / Make Good'")
        && str_contains($campaignsApi, "c.campaign_type=?")
        && str_contains($campaignsApi, 'reward_template_status'),
    'direct and invite endpoints reuse one guarded canonical send service' =>
        str_contains($sendApi, "require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php'")
        && str_contains($sendApi, 'mg_crm_campaign_send_execute')
        && str_contains($sendApi, 'mg_require_csrf_for_write($input)')
        && str_contains($inviteApi, "require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php'")
        && str_contains($inviteApi, 'mg_crm_campaign_invite_execute')
        && str_contains($inviteApi, 'mg_require_csrf_for_write($input)')
        && str_contains($sendService, 'required_campaign_type')
        && str_contains($sendService, 'FOR UPDATE')
        && str_contains($sendService, 'crm_idempotency_key')
        && str_contains($sendService, 'mg_crm_campaign_send_for_contact')
        && str_contains($sendService, 'mg_crm_campaign_invite_execute'),
    'simplified action center remains responsive' =>
        str_contains($actionCss, '.mg-crm-make-good-center')
        && str_contains($actionCss, '.mg-crm-make-good-list')
        && str_contains($actionCss, '@media(max-width:760px)'),
    'feature is additive and requires no SQL' =>
        !str_contains($reporting, 'CREATE TABLE')
        && !str_contains($reporting, 'ALTER TABLE')
        && !str_contains($reportingApi, 'CREATE TABLE')
        && !str_contains($reportingApi, 'ALTER TABLE'),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) $failed[] = $name;
}
if ($failed !== []) {
    fwrite(STDERR, 'Merchant CRM reporting and make-good validation failed: ' . implode('; ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Merchant CRM reporting and make-good contract: 10/10 (' . count($checks) . ' checks).' . PHP_EOL;

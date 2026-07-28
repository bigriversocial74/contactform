<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm-campaign-send-service.php';

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

try {
    $result = mg_crm_campaign_invite_execute($pdo, $merchantId, $user, $input);
    mg_ok($result, !empty($result['duplicate']) ? 'CRM reward invite already sent.' : 'CRM reward invite sent.', !empty($result['duplicate']) ? 200 : 201);
} catch (MgCrmCampaignSendException $error) {
    mg_fail($error->getMessage(), $error->httpStatus, $error->context);
} catch (Throwable $error) {
    mg_security_log('error', 'merchant.crm_reward_invite.failed', 'Unable to send CRM reward invite.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to send CRM reward invite.', 500);
}

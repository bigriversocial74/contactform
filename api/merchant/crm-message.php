<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__) . '/communications/_communications.php';
require_once dirname(__DIR__) . '/communications/_delivery.php';
require_once dirname(__DIR__, 2) . '/includes/merchant-crm.php';

function mg_crm_message_public_id(): string { return mg_public_uuid(); }

mg_require_method('POST');
$user = mg_require_permission('merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);
$input = mg_input();
mg_require_csrf_for_write($input);

$contactRef = strtolower(trim((string)($input['contact_id'] ?? $input['contact'] ?? '')));
$body = mg_message_validate_body($input['message'] ?? $input['body'] ?? '');
if ($contactRef === '' || strlen($contactRef) !== 36) mg_fail('Contact is required.', 422);

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT cc.*,c.public_id campaign_public_id,c.title campaign_title,c.campaign_type FROM campaign_contacts cc INNER JOIN campaigns c ON c.id=cc.campaign_id WHERE cc.public_id=? AND cc.merchant_user_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$contactRef, $merchantId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) { $pdo->rollBack(); mg_fail('CRM contact not found.', 404); }

    $recipientId = (int)($contact['user_id'] ?? 0);
    $result = ['delivered_via' => 'email_fallback', 'notification_id' => null, 'email_delivery' => null];

    if ($recipientId > 0) {
        $notificationId = mg_create_notification($pdo, $recipientId, 'merchant_crm_message', 'New message from a merchant', $body, '/messages.php', [
            'actor_user_id' => $merchantId,
            'event_key' => 'crm.message.' . mg_crm_message_public_id(),
            'campaign_id' => (int)$contact['campaign_id'],
            'contact_id' => (int)$contact['id'],
            'campaign_type' => (string)$contact['campaign_type'],
        ]);
        $result = ['delivered_via' => 'microgifter_notification', 'notification_id' => $notificationId ?: null, 'email_delivery' => null];
    } elseif (filter_var((string)$contact['email'], FILTER_VALIDATE_EMAIL)) {
        $result['email_delivery'] = mg_delivery_enqueue($pdo, [
            'idempotency_key' => 'crm-email:' . $merchantId . ':' . $contactRef . ':' . hash('sha256', $body),
            'event_type' => 'campaign.outbound_email',
            'category' => 'campaign',
            'channel' => 'email',
            'template_key' => 'campaign.merchant_crm_message',
            'recipient_user_id' => 0,
            'recipient_snapshot' => ['email' => (string)$contact['email'], 'name' => (string)($contact['name'] ?? '')],
            'payload' => ['subject' => 'Microgifter merchant message', 'text' => $body, 'message_type' => 'merchant_crm_message'],
            'max_attempts' => 3,
        ]);
    }

    mg_merchant_crm_record_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_id' => (int)$contact['campaign_id'],
        'campaign_type' => (string)$contact['campaign_type'],
        'event_type' => 'crm.message.sent',
        'source_type' => 'merchant_crm_message',
        'source_public_id' => (string)$contact['public_id'],
        'user_id' => $recipientId > 0 ? $recipientId : null,
        'email' => (string)$contact['email'],
        'name' => (string)($contact['name'] ?? ''),
        'metadata' => $result,
    ]);

    $pdo->commit();
    mg_ok(['contact_id' => (string)$contact['public_id'], 'campaign_id' => (string)$contact['campaign_public_id'], 'campaign_type' => (string)$contact['campaign_type'], 'message' => $result], 'CRM message queued.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'merchant.crm_message.failed', 'Unable to send CRM message.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to send CRM message.', 500);
}

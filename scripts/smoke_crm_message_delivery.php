<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once dirname(__DIR__) . '/api/communications/_communications.php';

$pdo = mg_db();
$runId = gmdate('YmdHis') . '-' . bin2hex(random_bytes(3));
$merchantEmail = 'crm-smoke-merchant-' . $runId . '@example.test';
$customerEmail = 'crm-smoke-customer-' . $runId . '@example.test';

function smoke_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', random_int(0,65535), random_int(0,65535), random_int(0,65535), random_int(0,4095)|0x4000, random_int(0,0x3fff)|0x8000, random_int(0,65535), random_int(0,65535), random_int(0,65535));
}

function smoke_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

foreach (['users','campaigns','campaign_contacts','message_threads','message_thread_participants','messages','notifications'] as $table) {
    if (!smoke_table_exists($pdo, $table)) {
        fwrite(STDERR, "Missing required table: {$table}\n");
        exit(1);
    }
}

$pdo->beginTransaction();
try {
    $password = password_hash('microgifter-smoke-test', PASSWORD_DEFAULT);
    $userInsert = $pdo->prepare("INSERT INTO users (email,password_hash,full_name,display_name,status,email_verified_at,created_at,updated_at) VALUES (?,?,?,?, 'active', NOW(), NOW(), NOW())");
    $userInsert->execute([$merchantEmail, $password, 'Smoke Merchant', 'Smoke Merchant']);
    $merchantId = (int)$pdo->lastInsertId();
    $userInsert->execute([$customerEmail, $password, 'Smoke Customer', 'Smoke Customer']);
    $customerId = (int)$pdo->lastInsertId();

    $campaignPublicId = smoke_uuid();
    $pdo->prepare("INSERT INTO campaigns (public_id,merchant_user_id,campaign_type,title,status,created_at,updated_at) VALUES (?,?, 'newsletter_signup', 'CRM Smoke Campaign', 'active', NOW(), NOW())")
        ->execute([$campaignPublicId, $merchantId]);
    $campaignId = (int)$pdo->lastInsertId();

    $contactPublicId = smoke_uuid();
    $pdo->prepare("INSERT INTO campaign_contacts (public_id,merchant_user_id,campaign_id,user_id,email,name,source,opt_in_status,created_at,updated_at) VALUES (?,?,?,?,?,?,'newsletter_signup','opted_in',NOW(),NOW())")
        ->execute([$contactPublicId, $merchantId, $campaignId, null, $customerEmail, 'Smoke Customer']);
    $contactId = (int)$pdo->lastInsertId();

    $resolver = $pdo->prepare('UPDATE campaign_contacts cc JOIN users u ON LOWER(u.email)=LOWER(cc.email) SET cc.user_id=u.id,cc.updated_at=NOW() WHERE cc.id=? AND cc.user_id IS NULL');
    $resolver->execute([$contactId]);

    $threadPublicId = smoke_uuid();
    $conversationKey = 'crm:' . $contactPublicId;
    $pdo->prepare('INSERT INTO message_threads (public_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())')
        ->execute([$threadPublicId, $conversationKey, $merchantId, 'CRM: Smoke Customer']);
    $threadId = (int)$pdo->lastInsertId();

    $participant = $pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at,last_read_at) VALUES (?,?,NOW(),NULL)');
    $participant->execute([$threadId, $merchantId]);
    $participant->execute([$threadId, $customerId]);

    $messagePublicId = smoke_uuid();
    $pdo->prepare("INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([$messagePublicId, $threadId, $merchantId, $customerId, 'CRM message smoke test', 'crm-smoke:' . $runId, 'merchant_crm_message', $contactPublicId]);

    $notificationPublicId = mg_create_notification($pdo, $customerId, 'message', 'New merchant CRM message', 'CRM message smoke test', '/messages.php?thread=' . rawurlencode($threadPublicId), [
        'actor_user_id' => $merchantId,
        'event_key' => 'crm.smoke.' . strtolower($messagePublicId),
        'campaign_id' => $campaignId,
        'contact_id' => $contactId,
        'thread_id' => $threadId,
        'thread_public_id' => $threadPublicId,
        'message_id' => $messagePublicId,
        'source_system' => 'merchant_crm',
        'source_channel' => 'campaign_contacts',
        'source_label' => 'Merchant CRM',
    ]);

    $checks = [
        'contact_linked' => (bool)$pdo->query('SELECT 1 FROM campaign_contacts WHERE id=' . $contactId . ' AND user_id=' . $customerId)->fetchColumn(),
        'customer_participant' => (bool)$pdo->query('SELECT 1 FROM message_thread_participants WHERE thread_id=' . $threadId . ' AND user_id=' . $customerId)->fetchColumn(),
        'merchant_participant' => (bool)$pdo->query('SELECT 1 FROM message_thread_participants WHERE thread_id=' . $threadId . ' AND user_id=' . $merchantId)->fetchColumn(),
        'message_visible' => (bool)$pdo->query("SELECT 1 FROM messages WHERE public_id='" . addslashes($messagePublicId) . "' AND recipient_user_id=" . $customerId . " AND source_type='merchant_crm_message'")->fetchColumn(),
        'notification_created' => $notificationPublicId !== '',
    ];

    if (in_array(false, $checks, true)) {
        throw new RuntimeException('CRM message smoke check failed: ' . json_encode($checks));
    }

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'merchant_user_id' => $merchantId,
        'customer_user_id' => $customerId,
        'contact_id' => $contactPublicId,
        'thread_id' => $threadPublicId,
        'message_id' => $messagePublicId,
        'notification_id' => $notificationPublicId,
        'checks' => $checks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

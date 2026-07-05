<?php

declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

mg_require_method('GET');
$user = mg_merchant_require_permission('merchant.campaigns.view');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo, $user);

$contactRef = strtolower(trim((string)($_GET['contact_id'] ?? $_GET['contact'] ?? '')));
if ($contactRef === '' || strlen($contactRef) !== 36 || !preg_match('/^[a-f0-9-]{36}$/', $contactRef)) {
    mg_fail('Invalid CRM contact.', 422);
}

try {
    $contactStmt = $pdo->prepare('SELECT id, public_id, email, name FROM campaign_contacts WHERE public_id=? AND merchant_user_id=? LIMIT 1');
    $contactStmt->execute([$contactRef, $merchantId]);
    $contact = $contactStmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) mg_fail('CRM contact not found.', 404);

    $stmt = $pdo->prepare("SELECT ce.public_id, ce.event_type, ce.event_context_json, ce.created_at, c.public_id campaign_public_id, c.title campaign_title, c.campaign_type, wi.public_id wallet_public_id, wi.status wallet_status, wi.title_snapshot
        FROM campaign_events ce
        LEFT JOIN campaigns c ON c.id=ce.campaign_id
        LEFT JOIN wallet_items wi ON wi.id=ce.wallet_item_id
        WHERE ce.merchant_user_id=? AND ce.event_type='crm.campaign_reward.sent' AND (ce.contact_id=? OR JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.source_contact_id'))=? OR JSON_UNQUOTE(JSON_EXTRACT(ce.event_context_json,'$.target_contact_id'))=?)
        ORDER BY ce.created_at DESC, ce.id DESC
        LIMIT 25");
    $stmt->execute([$merchantId, (int)$contact['id'], $contactRef, $contactRef]);
    $history = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ctx = json_decode((string)($row['event_context_json'] ?? ''), true);
        if (!is_array($ctx)) $ctx = [];
        $history[] = [
            'id' => (string)$row['public_id'],
            'event_type' => (string)$row['event_type'],
            'campaign_id' => (string)($row['campaign_public_id'] ?? ($ctx['target_campaign_id'] ?? '')),
            'campaign_title' => (string)($row['campaign_title'] ?? ''),
            'campaign_type' => (string)($row['campaign_type'] ?? ($ctx['campaign_type'] ?? '')),
            'campaign_type_label' => (string)($ctx['campaign_type_label'] ?? ''),
            'reward_template_id' => (string)($ctx['reward_template_id'] ?? ''),
            'wallet_item_id' => (string)($row['wallet_public_id'] ?? ($ctx['wallet_item_id'] ?? '')),
            'wallet_status' => (string)($row['wallet_status'] ?? ''),
            'title' => (string)($row['title_snapshot'] ?? ''),
            'note' => (string)($ctx['note'] ?? ''),
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    mg_ok(['contact' => ['id' => (string)$contact['public_id'], 'email' => (string)$contact['email'], 'name' => (string)($contact['name'] ?? '')], 'history' => $history, 'count' => count($history)]);
} catch (Throwable $error) {
    mg_security_log('warning', 'merchant.crm_campaign_send_history.unavailable', 'CRM campaign send history unavailable.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_ok(['history' => [], 'count' => 0], 'Campaign send history unavailable.');
}

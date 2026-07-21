<?php
declare(strict_types=1);

function mg_mcp_conversion_create_gift(PDO $pdo, array $user, array $draft): array
{
    mg_mcp_conversion_require_permission($user, 'gift');
    require_once dirname(__DIR__, 2) . '/api/gifts/_gift.php';
    $payload = mg_mcp_draft_json($draft['payload_json'] ?? null);
    $productId = (string)($payload['product_id'] ?? '');
    $stmt = $pdo->prepare(
        "SELECT p.public_id,p.merchant_user_id,p.status,v.public_id AS product_version_id,v.title,v.description,v.unit_value_cents,v.currency
         FROM catalog_products p
         INNER JOIN catalog_product_versions v ON v.id=p.current_version_id
         WHERE p.public_id=? AND p.status='published' AND v.version_status='published'
         LIMIT 1"
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new MgMcpDraftException(
            'The selected product is no longer available.',
            409,
            'MCP_CONVERSION_PRODUCT_UNAVAILABLE'
        );
    }
    $quantity = max(1, min(25, (int)($payload['quantity'] ?? 1)));
    $valueCents = max(0, (int)$product['unit_value_cents']) * $quantity;
    $recipientName = trim((string)($payload['recipient_name'] ?? '')) ?: 'Recipient';
    $message = trim((string)($payload['message'] ?? ''));
    $description = $message !== '' ? $message : trim((string)($product['description'] ?? ''));
    $giftPublicId = mg_gift_public_id();
    $metadata = [
        'mcp_source_draft_id' => (string)$draft['public_id'],
        'source_product_id' => $productId,
        'source_product_version_id' => (string)$product['product_version_id'],
        'source_merchant_user_id' => (int)$product['merchant_user_id'],
        'quantity' => $quantity,
        'recipient_reference' => (string)($payload['recipient_reference'] ?? ''),
        'message' => $message,
        'deliver_after' => $payload['deliver_after'] ?? null,
        'notes' => (string)($payload['notes'] ?? ''),
        'conversion_phase' => '3b',
        'converted_by_user_id' => (int)$user['id'],
        'execution_enabled' => false,
        'payment_created' => false,
        'gift_issued' => false,
    ];
    $pdo->prepare(
        "INSERT INTO gifts
         (public_id,slug,sender_user_id,recipient_name,title,description,gift_type,value_cents,currency,visibility,status,metadata_json,created_at,updated_at)
         VALUES (?,NULL,?,?,?,?,?,?,?,'private','draft',?,NOW(),NOW())"
    )->execute([
        $giftPublicId,
        (int)$user['id'],
        mb_substr($recipientName, 0, 120),
        mb_substr('Gift: ' . (string)$product['title'], 0, 160),
        $description !== '' ? mb_substr($description, 0, 5000) : null,
        'Product gift draft',
        $valueCents,
        strtoupper(substr((string)$product['currency'], 0, 3)),
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    $giftDbId = (int)$pdo->lastInsertId();
    mg_gift_event($pdo, $giftDbId, (int)$user['id'], 'created', [
        'action' => 'mcp_phase3b_native_draft_created',
        'mcp_source_draft_id' => (string)$draft['public_id'],
        'execution_enabled' => false,
    ]);
    return [
        'native_public_id' => $giftPublicId,
        'native_url' => '/product.php?id=' . rawurlencode($productId) . '&gift_draft=' . rawurlencode($giftPublicId),
        'evidence' => ['product_id' => $productId, 'quantity' => $quantity, 'payment_created' => false],
    ];
}

function mg_mcp_conversion_create_campaign(PDO $pdo, array $user, array $draft): array
{
    $workspace = mg_mcp_conversion_workspace($pdo, $draft, (int)$user['id']);
    mg_mcp_conversion_require_permission($user, 'campaign', $workspace);
    mg_mcp_conversion_require_merchant_package($pdo, $user, $workspace);
    $payload = mg_mcp_draft_json($draft['payload_json'] ?? null);
    $nativeId = 'mcp_' . substr(hash('sha256', (string)$draft['public_id']), 0, 24);
    $context = [
        'draft_id' => $nativeId,
        'campaign_name' => (string)($payload['name'] ?? $draft['title']),
        'segment_id' => '',
        'segment_key' => 'all',
        'message' => (string)($payload['offer_summary'] ?? ''),
        'reward_template_id' => '',
        'note' => implode("\n\n", array_values(array_filter([
            (string)($payload['objective'] ?? ''),
            (string)($payload['audience_summary'] ?? ''),
            (string)($payload['notes'] ?? ''),
        ]))),
        'follow_up_due_at' => (string)($payload['starts_at'] ?? ''),
        'follow_up_note' => (string)($payload['ends_at'] ?? ''),
        'budget_cents' => isset($payload['budget_cents']) ? (int)$payload['budget_cents'] : null,
        'status' => 'draft',
        'mcp_source_draft_id' => (string)$draft['public_id'],
        'mcp_conversion_phase' => '3b',
        'converted_by_user_id' => (int)$user['id'],
        'execution_enabled' => false,
    ];
    $pdo->prepare(
        'INSERT INTO campaign_events
         (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at)
         VALUES (?,?,NULL,NULL,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        (int)$workspace['merchant_user_id'],
        'crm.campaign_builder.draft',
        json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    return [
        'native_public_id' => $nativeId,
        'native_url' => '/merchant-crm.php?mcp_campaign_draft=' . rawurlencode($nativeId),
        'evidence' => ['merchant_user_id' => (int)$workspace['merchant_user_id'], 'launched' => false],
    ];
}

function mg_mcp_conversion_create_reward(PDO $pdo, array $user, array $draft): array
{
    $workspace = mg_mcp_conversion_workspace($pdo, $draft, (int)$user['id']);
    mg_mcp_conversion_require_permission($user, 'reward', $workspace);
    $merchantUserId = (int)$workspace['merchant_user_id'];
    $usageStmt = $pdo->prepare("SELECT COUNT(*) FROM reward_templates WHERE merchant_user_id=? AND status<>'archived'");
    $usageStmt->execute([$merchantUserId]);
    mg_mcp_conversion_require_merchant_package($pdo, $user, $workspace, 'max_rewards', (int)$usageStmt->fetchColumn());

    $payload = mg_mcp_draft_json($draft['payload_json'] ?? null);
    $publicId = mg_public_uuid();
    $description = implode("\n\n", array_values(array_filter([
        (string)($payload['qualification_summary'] ?? ''),
        (string)($payload['reward_summary'] ?? ''),
        (string)($payload['notes'] ?? ''),
    ])));
    $endsAt = trim((string)($payload['ends_at'] ?? ''));
    $metadata = [
        'mcp_source_draft_id' => (string)$draft['public_id'],
        'qualification_summary' => (string)($payload['qualification_summary'] ?? ''),
        'starts_at' => $payload['starts_at'] ?? null,
        'conversion_phase' => '3b',
        'converted_by_user_id' => (int)$user['id'],
        'execution_enabled' => false,
        'activated' => false,
        'fulfilled' => false,
    ];
    $pdo->prepare(
        "INSERT INTO reward_templates
         (public_id,merchant_user_id,title,description,reward_type,value_type,value_amount_cents,value_percent,currency,
          redemption_instructions,expiration_rule,expiration_days,expires_at,quantity_limit,per_user_limit,
          agent_discoverable,agent_summary,agent_categories_json,agent_use_cases_json,agent_add_to_wallet_allowed,
          agent_gift_send_allowed,status,metadata_json,created_at,updated_at)
         VALUES (?,?,?,?,'custom','custom',0,NULL,'USD',?,?,NULL,?,?,1,0,NULL,NULL,NULL,0,0,'draft',?,NOW(),NOW())"
    )->execute([
        $publicId,
        $merchantUserId,
        mb_substr((string)($payload['name'] ?? $draft['title']), 0, 180),
        $description !== '' ? $description : null,
        (string)($payload['reward_summary'] ?? ''),
        $endsAt !== '' ? 'fixed_date' : 'none',
        $endsAt !== '' ? $endsAt : null,
        isset($payload['quantity_limit']) ? (int)$payload['quantity_limit'] : null,
        json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    return [
        'native_public_id' => $publicId,
        'native_url' => '/merchant-reward-templates.php?template=' . rawurlencode($publicId),
        'evidence' => ['merchant_user_id' => $merchantUserId, 'status' => 'draft', 'activated' => false],
    ];
}

function mg_mcp_conversion_create_message(PDO $pdo, array $user, array $draft): array
{
    $workspace = mg_mcp_conversion_workspace($pdo, $draft, (int)$user['id']);
    mg_mcp_conversion_require_permission($user, 'message', $workspace);
    mg_mcp_conversion_require_merchant_package($pdo, $user, $workspace);
    $payload = mg_mcp_draft_json($draft['payload_json'] ?? null);
    $nativeId = 'mcpmsg_' . substr(hash('sha256', (string)$draft['public_id']), 0, 24);
    $context = [
        'message_draft_id' => $nativeId,
        'execution_id' => '',
        'approval_id' => '',
        'playbook_key' => 'mcp_external_draft',
        'playbook_title' => (string)($payload['subject'] ?? $draft['title']),
        'customer_name' => '',
        'customer_email' => '',
        'campaign_title' => (string)($payload['audience_summary'] ?? ''),
        'draft_body' => (string)($payload['body'] ?? ''),
        'message_body' => (string)($payload['body'] ?? ''),
        'why' => 'Approved MCP draft converted by the owner into an inactive merchant message draft.',
        'guardrail_applied' => 'A separate merchant action is required before sending.',
        'expected_action' => 'Review or edit this message draft before any customer communication.',
        'channel' => (string)($payload['channel'] ?? 'in_app'),
        'schedule_after' => $payload['schedule_after'] ?? null,
        'notes' => (string)($payload['notes'] ?? ''),
        'mcp_source_draft_id' => (string)$draft['public_id'],
        'mcp_conversion_phase' => '3b',
        'converted_by_user_id' => (int)$user['id'],
        'execution_enabled' => false,
    ];
    $pdo->prepare(
        'INSERT INTO campaign_events
         (public_id,merchant_user_id,campaign_id,contact_id,event_type,event_context_json,created_at)
         VALUES (?,?,NULL,NULL,?,?,NOW())'
    )->execute([
        mg_public_uuid(),
        (int)$workspace['merchant_user_id'],
        'crm.agent.message.draft.created',
        json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    return [
        'native_public_id' => $nativeId,
        'native_url' => '/merchant-agent-messages.php?draft=' . rawurlencode($nativeId),
        'evidence' => ['merchant_user_id' => (int)$workspace['merchant_user_id'], 'sent' => false],
    ];
}

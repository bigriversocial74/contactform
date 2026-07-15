<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/account/_commerce.php';
require_once dirname(__DIR__, 2) . '/api/profiles/_product_discovery.php';

function mg_personal_agent_message_has_secret_request(string $message): bool
{
    $message = mb_strtolower($message);
    foreach ([
        'claim code', 'redemption code', 'redeem code', 'qr secret', 'secret code',
        'password', 'access token', 'api token', 'private key', 'card number', 'cvv',
    ] as $phrase) {
        if (str_contains($message, $phrase)) return true;
    }
    return false;
}

function mg_personal_agent_message_mentions(string $message, array $terms): bool
{
    $message = mb_strtolower($message);
    foreach ($terms as $term) {
        if (str_contains($message, mb_strtolower((string) $term))) return true;
    }
    return false;
}

function mg_personal_agent_marketplace_category(string $message): string
{
    $message = mb_strtolower($message);
    $categories = [
        'coffee' => ['coffee', 'cafe', 'espresso', 'latte'],
        'restaurant' => ['restaurant', 'dinner', 'lunch', 'breakfast', 'food', 'meal'],
        'bar' => ['bar', 'nightlife', 'cocktail', 'beer', 'wine'],
        'event' => ['event', 'concert', 'show', 'ticket', 'venue'],
        'fitness' => ['fitness', 'gym', 'workout', 'wellness', 'yoga'],
        'retail' => ['retail', 'shop', 'shopping', 'store', 'product'],
        'service' => ['service', 'appointment', 'salon', 'spa'],
        'creator' => ['creator', 'artist', 'maker', 'music'],
    ];
    foreach ($categories as $category => $terms) {
        foreach ($terms as $term) {
            if (str_contains($message, $term)) return $category;
        }
    }
    return '';
}

function mg_personal_agent_contact_knowledge(PDO $pdo, int $userId, int $limit = 160): array
{
    if (!mg_personal_agent_table_exists($pdo, 'user_contacts')) return [];
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->prepare("SELECT c.public_id,c.display_name,c.nickname,c.relationship_type,c.relationship_label,c.birthdate,
        c.company,c.job_title,c.city,c.state_region,c.country_code,c.interests,c.gift_preferences,
        c.allergies_or_restrictions,c.preferred_merchants,c.preferred_categories,c.budget_min,c.budget_max,
        GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') list_names
        FROM user_contacts c
        LEFT JOIN user_contact_list_members m ON m.user_contact_id=c.id AND m.owner_user_id=c.owner_user_id
        LEFT JOIN user_contact_lists l ON l.id=m.list_id AND l.owner_user_id=c.owner_user_id AND l.is_archived=0
        WHERE c.owner_user_id=? AND c.archived_at IS NULL
        GROUP BY c.id
        ORDER BY c.display_name
        LIMIT {$limit}");
    $stmt->execute([$userId]);
    return array_map(static function (array $row): array {
        return [
            'id' => (string) $row['public_id'],
            'name' => (string) $row['display_name'],
            'nickname' => (string) ($row['nickname'] ?? ''),
            'relationship' => trim((string) ($row['relationship_label'] ?: $row['relationship_type'] ?? '')),
            'birthdate' => $row['birthdate'] ?: null,
            'company' => (string) ($row['company'] ?? ''),
            'job_title' => (string) ($row['job_title'] ?? ''),
            'location' => trim(implode(', ', array_filter([
                (string) ($row['city'] ?? ''),
                (string) ($row['state_region'] ?? ''),
                (string) ($row['country_code'] ?? ''),
            ]))),
            'interests' => mg_personal_agent_text($row['interests'] ?? '', 1200),
            'gift_preferences' => mg_personal_agent_text($row['gift_preferences'] ?? '', 1200),
            'restrictions' => mg_personal_agent_text($row['allergies_or_restrictions'] ?? '', 800),
            'preferred_merchants' => mg_personal_agent_text($row['preferred_merchants'] ?? '', 800),
            'preferred_categories' => mg_personal_agent_text($row['preferred_categories'] ?? '', 800),
            'budget_min' => $row['budget_min'] !== null ? (float) $row['budget_min'] : null,
            'budget_max' => $row['budget_max'] !== null ? (float) $row['budget_max'] : null,
            'lists' => (string) ($row['list_names'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_personal_agent_list_knowledge(PDO $pdo, int $userId, int $limit = 80): array
{
    if (!mg_personal_agent_table_exists($pdo, 'user_contact_lists')) return [];
    return array_map(static fn(array $list): array => [
        'id' => (string) ($list['id'] ?? ''),
        'name' => (string) ($list['name'] ?? ''),
        'description' => mg_personal_agent_text($list['description'] ?? '', 700),
        'type' => (string) ($list['list_type'] ?? ''),
        'member_count' => (int) ($list['member_count'] ?? 0),
        'next_birthday' => $list['next_birthday'] ?? null,
    ], array_slice(mg_user_contact_lists($pdo, $userId, false), 0, max(1, min(150, $limit))));
}

function mg_personal_agent_order_knowledge(PDO $pdo, int $userId, int $limit = 80): array
{
    if (!mg_personal_agent_table_exists($pdo, 'commerce_orders') || !mg_personal_agent_table_exists($pdo, 'commerce_order_items')) return [];
    $limit = max(1, min(150, $limit));
    $stmt = $pdo->prepare("SELECT o.id,o.public_id,o.currency,o.subtotal_cents,o.discount_cents,o.tax_cents,o.platform_fee_cents,o.total_cents,
        o.payment_status,o.fulfillment_status,o.paid_at,o.cancelled_at,o.created_at,o.updated_at
        FROM commerce_orders o WHERE o.buyer_user_id=? ORDER BY o.created_at DESC,o.id DESC LIMIT {$limit}");
    $stmt->execute([$userId]);
    $orders = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $order) {
        $items = $pdo->prepare('SELECT title_snapshot,quantity,unit_amount_cents,line_total_cents,currency FROM commerce_order_items WHERE order_id=? ORDER BY id LIMIT 40');
        $items->execute([(int) $order['id']]);
        $orders[] = [
            'id' => (string) $order['public_id'],
            'currency' => (string) $order['currency'],
            'subtotal_cents' => (int) $order['subtotal_cents'],
            'discount_cents' => (int) $order['discount_cents'],
            'tax_cents' => (int) $order['tax_cents'],
            'platform_fee_cents' => (int) $order['platform_fee_cents'],
            'total_cents' => (int) $order['total_cents'],
            'payment_status' => (string) $order['payment_status'],
            'fulfillment_status' => (string) $order['fulfillment_status'],
            'paid_at' => $order['paid_at'] ?: null,
            'cancelled_at' => $order['cancelled_at'] ?: null,
            'created_at' => (string) $order['created_at'],
            'items' => array_map(static fn(array $item): array => [
                'title' => (string) $item['title_snapshot'],
                'quantity' => (int) $item['quantity'],
                'unit_amount_cents' => (int) $item['unit_amount_cents'],
                'line_total_cents' => (int) $item['line_total_cents'],
                'currency' => (string) $item['currency'],
            ], $items->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }
    return $orders;
}

function mg_personal_agent_safe_item(array $item): array
{
    return [
        'id' => (string) ($item['item_id'] ?? ''),
        'title' => (string) ($item['title_snapshot'] ?? 'Microgifter item'),
        'description' => mg_personal_agent_text($item['description_snapshot'] ?? '', 500),
        'item_type' => (string) ($item['item_type'] ?? ''),
        'funding_type' => (string) ($item['funding_type'] ?? ''),
        'value_cents' => (int) ($item['value_cents_snapshot'] ?? 0),
        'currency' => (string) ($item['currency_snapshot'] ?? 'USD'),
        'status' => (string) ($item['status'] ?? ''),
        'merchant_name' => (string) ($item['merchant_name'] ?? ''),
        'issuer_name' => (string) ($item['issuer_name'] ?? ''),
        'recipient_name' => (string) ($item['recipient_name'] ?? ''),
        'issued_at' => $item['issued_at'] ?? null,
        'sent_at' => $item['sent_at'] ?? null,
        'claimed_at' => $item['claimed_at'] ?? null,
        'redeemed_at' => $item['redeemed_at'] ?? null,
        'expires_at' => $item['expires_at'] ?? null,
    ];
}

function mg_personal_agent_safe_gift(array $gift): array
{
    return [
        'id' => (string) ($gift['gift_id'] ?? ''),
        'title' => (string) ($gift['title'] ?? 'Gift'),
        'description' => mg_personal_agent_text($gift['description'] ?? '', 500),
        'gift_type' => (string) ($gift['gift_type'] ?? ''),
        'value_cents' => (int) ($gift['value_cents'] ?? 0),
        'currency' => (string) ($gift['currency'] ?? 'USD'),
        'status' => (string) ($gift['status'] ?? ''),
        'claim_status' => (string) ($gift['claim_status'] ?? ''),
        'item_status' => (string) ($gift['pppm_status'] ?? ''),
        'sender_name' => (string) ($gift['sender_name'] ?? ''),
        'recipient_name' => (string) ($gift['recipient_display_name'] ?? $gift['recipient_name'] ?? ''),
        'sent_at' => $gift['sent_at'] ?? null,
        'delivered_at' => $gift['delivered_at'] ?? null,
        'claimed_at' => $gift['claimed_at'] ?? null,
        'expires_at' => $gift['expires_at'] ?? null,
    ];
}

function mg_personal_agent_safe_claim(array $claim): array
{
    return [
        'id' => (string) ($claim['claim_id'] ?? ''),
        'gift_id' => (string) ($claim['gift_id'] ?? ''),
        'title' => (string) ($claim['title'] ?? 'Gift claim'),
        'description' => mg_personal_agent_text($claim['description'] ?? '', 500),
        'gift_type' => (string) ($claim['gift_type'] ?? ''),
        'value_cents' => (int) ($claim['value_cents'] ?? 0),
        'currency' => (string) ($claim['currency'] ?? 'USD'),
        'status' => (string) ($claim['status'] ?? ''),
        'gift_status' => (string) ($claim['gift_status'] ?? ''),
        'item_status' => (string) ($claim['pppm_status'] ?? ''),
        'sender_name' => (string) ($claim['sender_name'] ?? ''),
        'verified_at' => $claim['verified_at'] ?? null,
        'redeemed_at' => $claim['redeemed_at'] ?? null,
        'expires_at' => $claim['expires_at'] ?? null,
        'updated_at' => $claim['updated_at'] ?? null,
    ];
}

function mg_personal_agent_commerce_knowledge(PDO $pdo, int $userId): array
{
    $orders = mg_personal_agent_order_knowledge($pdo, $userId, 80);
    $purchased = $owned = $sentItems = $receivedItems = $sentGifts = $receivedGifts = $claims = [];
    try {
        if (mg_personal_agent_table_exists($pdo, 'pppm_items')) {
            $purchased = array_map('mg_personal_agent_safe_item', mg_account_items($pdo, $userId, 'purchased', 80));
            $owned = array_map('mg_personal_agent_safe_item', mg_account_items($pdo, $userId, 'owned', 80));
            $sentItems = array_map('mg_personal_agent_safe_item', mg_account_items($pdo, $userId, 'sent', 60));
            $receivedItems = array_map('mg_personal_agent_safe_item', mg_account_items($pdo, $userId, 'received', 60));
        }
        if (mg_personal_agent_table_exists($pdo, 'gifts')) {
            $sentGifts = array_map('mg_personal_agent_safe_gift', mg_account_gifts($pdo, $userId, 'sent', 60));
            $receivedGifts = array_map('mg_personal_agent_safe_gift', mg_account_gifts($pdo, $userId, 'received', 60));
        }
        if (mg_personal_agent_table_exists($pdo, 'gift_claims')) {
            $claims = array_map('mg_personal_agent_safe_claim', mg_account_claims($pdo, $userId, 'all', 80));
        }
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'user_agent.commerce_context_partial', 'Personal agent commerce context was partially unavailable.', ['exception_type' => $error::class], $userId);
        }
    }
    return [
        'summary' => [
            'orders' => count($orders),
            'purchased_items' => count($purchased),
            'owned_items' => count($owned),
            'sent_items' => count($sentItems),
            'received_items' => count($receivedItems),
            'sent_gifts' => count($sentGifts),
            'received_gifts' => count($receivedGifts),
            'claims' => count($claims),
        ],
        'orders' => $orders,
        'purchased_items' => $purchased,
        'owned_items' => $owned,
        'sent_items' => $sentItems,
        'received_items' => $receivedItems,
        'sent_gifts' => $sentGifts,
        'received_gifts' => $receivedGifts,
        'claims' => $claims,
    ];
}

function mg_personal_agent_marketplace_knowledge(PDO $pdo, int $userId, string $message): array
{
    $category = mg_personal_agent_marketplace_category($message);
    $filters = ['type' => 'merchant', 'sort' => 'active', 'limit' => 14];
    if ($category !== '') $filters['category'] = $category;
    try {
        $profiles = mg_profile_discovery_search($pdo, $filters, $userId);
        $products = mg_product_discovery_search($pdo, array_merge($filters, ['product_limit' => 20]), $userId);
        return [
            'filters' => ['category' => $category, 'scope' => 'public marketplace listings visible to this user'],
            'merchants' => array_map(static fn(array $merchant): array => [
                'id' => (string) ($merchant['id'] ?? ''),
                'name' => (string) ($merchant['display_name'] ?? ''),
                'business_name' => (string) ($merchant['business_name'] ?? ''),
                'headline' => mg_personal_agent_text($merchant['headline'] ?? '', 400),
                'location' => (string) ($merchant['location'] ?? ''),
                'profile_type' => (string) ($merchant['profile_type'] ?? ''),
                'published_products' => (int) ($merchant['published_products'] ?? 0),
                'published_campaigns' => (int) ($merchant['published_campaigns'] ?? 0),
                'url' => (string) ($merchant['url'] ?? ''),
            ], array_slice($profiles['items'] ?? [], 0, 14)),
            'products' => array_map(static fn(array $product): array => [
                'id' => (string) ($product['id'] ?? ''),
                'title' => (string) ($product['title'] ?? ''),
                'description' => mg_personal_agent_text($product['description'] ?? '', 500),
                'type' => (string) ($product['product_type'] ?? ''),
                'value_cents' => (int) ($product['value_cents'] ?? 0),
                'currency' => (string) ($product['currency'] ?? 'USD'),
                'merchant' => [
                    'name' => (string) ($product['merchant']['name'] ?? ''),
                    'url' => (string) ($product['merchant']['url'] ?? ''),
                    'store_url' => (string) ($product['merchant']['store_url'] ?? ''),
                ],
                'locations' => array_slice(is_array($product['locations'] ?? null) ? $product['locations'] : [], 0, 6),
                'url' => (string) ($product['url'] ?? ''),
                'purchase_available' => (bool) ($product['purchase_available'] ?? false),
            ], array_slice($products['items'] ?? [], 0, 20)),
        ];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning', 'user_agent.marketplace_context_partial', 'Personal agent marketplace context was unavailable.', ['exception_type' => $error::class], $userId);
        }
        return ['filters' => ['category' => $category, 'scope' => 'public marketplace listings'], 'merchants' => [], 'products' => []];
    }
}

function mg_personal_agent_knowledge(PDO $pdo, int $userId, string $message): array
{
    $contacts = mg_personal_agent_contact_knowledge($pdo, $userId);
    $lists = mg_personal_agent_list_knowledge($pdo, $userId);
    return [
        'privacy_scope' => [
            'account_data' => 'Owner-scoped information belonging to the signed-in user. Use it only to answer this user.',
            'marketplace_data' => 'Only public marketplace profiles and published product listings visible to this user.',
            'never_available' => 'Merchant-only claim codes, redemption credentials, full phone numbers, email addresses, street addresses, payment credentials, passwords, tokens, private merchant notes, hidden inventory, and restricted workspace data.',
        ],
        'account' => [
            'summary' => ['contacts' => count($contacts), 'lists' => count($lists)],
            'contacts' => $contacts,
            'lists' => $lists,
            'commerce' => mg_personal_agent_commerce_knowledge($pdo, $userId),
        ],
        'marketplace' => mg_personal_agent_marketplace_knowledge($pdo, $userId, $message),
    ];
}

function mg_personal_agent_system_prompt_v2(): string
{
    return <<<'PROMPT'
You are Microgifter's Personal Gifting Agent for the signed-in individual customer.

You may use two permission-scoped knowledge areas supplied in the request:
1. account: the signed-in user's own contacts, contact attributes, lists, purchases, orders, owned items, sent and received gifts, claim status, plans, reminders, dates, and Agent Memory.
2. marketplace: public merchant profiles and published products that the current user is permitted to discover on Microgifter.

Use this knowledge to answer normal questions directly. You can compare prior purchases, identify relevant contacts or lists, summarize commerce history, suggest public marketplace products, and prepare approval-first plans or reminders.

Hard privacy and permission rules:
- Never reveal, reconstruct, request, or guess claim codes, redemption credentials, full phone numbers, email addresses, street addresses, payment details, passwords, tokens, private keys, private merchant notes, hidden inventory, or restricted merchant/workspace fields.
- Claim status may be summarized, but claim codes and redemption secrets are merchant-permission-only and are never available to you.
- Treat account data as private to this signed-in user. Do not imply that another user, merchant, advertiser, or public visitor can access it.
- Treat marketplace data as public discovery knowledge only. Do not invent inventory, pricing, availability, campaigns, reviews, or merchant facts that are absent from the supplied data.
- Respect blocks, visibility, account ownership, merchant permissions, allergies, restrictions, budgets, timing, and relationship context.
- Do not purchase, send, schedule, charge, claim, redeem, transfer, or modify anything without a separate explicit reviewable action.
- Every proposed action is advisory or a reviewable draft.
- If the user asks a general question, answer conversationally instead of forcing a gift-plan card.
- Return valid JSON only, with no markdown fences and no prose outside JSON.

Return:
{
  "reply": "clear helpful response grounded in supplied data",
  "cards": [
    {
      "type": "recommendation|plan|reminder|warning|next_step",
      "title": "short title",
      "body": "specific recommendation",
      "reason": "why this fits the supplied account or marketplace context",
      "timing": "when to act",
      "warning": "optional constraint",
      "action": "save_draft_plan|create_reminder|open_list|open_contact|none",
      "action_label": "optional button label",
      "risk_level": "low|medium",
      "review_payload": {
        "title": "draft plan title",
        "occasion_type": "birthday|anniversary|holiday|thank_you|recognition|general",
        "occasion_label": "human label",
        "target_date": "YYYY-MM-DD or empty",
        "budget_min": 0,
        "budget_max": 0,
        "currency": "USD",
        "notes": "approval-first draft notes"
      }
    }
  ]
}
PROMPT;
}

function mg_personal_agent_fallback_v2(string $message, array $context, array $dashboard, array $knowledge): array
{
    if (mg_personal_agent_message_has_secret_request($message)) {
        return ['reply' => 'I can help explain claim status and redemption steps, but I cannot access or reveal claim codes, redemption credentials, payment details, or other merchant-restricted secrets.', 'cards' => []];
    }

    $account = is_array($knowledge['account'] ?? null) ? $knowledge['account'] : [];
    $commerce = is_array($account['commerce'] ?? null) ? $account['commerce'] : [];
    $marketplace = is_array($knowledge['marketplace'] ?? null) ? $knowledge['marketplace'] : [];

    if (mg_personal_agent_message_mentions($message, ['purchase', 'order', 'receipt', 'bought', 'paid'])) {
        $summary = is_array($commerce['summary'] ?? null) ? $commerce['summary'] : [];
        $orders = is_array($commerce['orders'] ?? null) ? $commerce['orders'] : [];
        $reply = 'Your account currently shows ' . (int) ($summary['orders'] ?? 0) . ' orders and ' . (int) ($summary['purchased_items'] ?? 0) . ' purchased items.';
        if ($orders !== []) {
            $latest = $orders[0];
            $titles = array_values(array_filter(array_map(static fn(array $item): string => (string) ($item['title'] ?? ''), $latest['items'] ?? [])));
            $reply .= ' Your most recent order' . ($titles ? ' includes ' . implode(', ', array_slice($titles, 0, 3)) : '') . ' and is marked ' . (string) ($latest['payment_status'] ?? 'unknown') . '.';
        }
        return ['reply' => $reply, 'cards' => []];
    }

    if (mg_personal_agent_message_mentions($message, ['contact', 'list', 'birthday', 'recipient', 'friend', 'family', 'coworker'])) {
        $contacts = is_array($account['contacts'] ?? null) ? $account['contacts'] : [];
        $lists = is_array($account['lists'] ?? null) ? $account['lists'] : [];
        $names = array_values(array_filter(array_map(static fn(array $contact): string => (string) ($contact['name'] ?? ''), array_slice($contacts, 0, 5))));
        $reply = 'I can use your ' . count($contacts) . ' contacts and ' . count($lists) . ' lists, including saved relationships, dates, interests, gift preferences, restrictions, preferred merchants, categories, and budgets.';
        if ($names) $reply .= ' Some available contacts are ' . implode(', ', $names) . '.';
        return ['reply' => $reply, 'cards' => []];
    }

    if (mg_personal_agent_message_mentions($message, ['merchant', 'marketplace', 'product', 'local', 'shop', 'store', 'gift'])) {
        $products = is_array($marketplace['products'] ?? null) ? $marketplace['products'] : [];
        $merchants = is_array($marketplace['merchants'] ?? null) ? $marketplace['merchants'] : [];
        $productNames = array_values(array_filter(array_map(static fn(array $product): string => (string) ($product['title'] ?? ''), array_slice($products, 0, 4))));
        $reply = 'I can use Microgifter’s public marketplace knowledge for this request. I found ' . count($merchants) . ' visible merchants and ' . count($products) . ' published products in the current marketplace context.';
        if ($productNames) $reply .= ' Examples include ' . implode(', ', $productNames) . '.';
        return ['reply' => $reply, 'cards' => []];
    }

    if (($context['type'] ?? 'none') !== 'none') return mg_personal_agent_fallback($message, $context, $dashboard);

    $summary = is_array($account['summary'] ?? null) ? $account['summary'] : [];
    $commerceSummary = is_array($commerce['summary'] ?? null) ? $commerce['summary'] : [];
    return [
        'reply' => 'I’m ready to help using your Microgifter account context: ' . (int) ($summary['contacts'] ?? 0) . ' contacts, ' . (int) ($summary['lists'] ?? 0) . ' lists, ' . (int) ($commerceSummary['orders'] ?? 0) . ' orders, and permission-safe public marketplace knowledge. Ask about a person, purchase, list, date, merchant, or product.',
        'cards' => [],
    ];
}

function mg_personal_agent_chat_v2(PDO $pdo, int $userId, array $input): array
{
    mg_personal_agent_require_schema($pdo);
    $message = mg_personal_agent_text($input['message'] ?? '', 2000);
    if ($message === '') throw new InvalidArgumentException('Enter a message for the Personal Gifting Agent.');

    $context = mg_personal_agent_resolve_context($pdo, $userId, (string) ($input['context_type'] ?? 'none'), (string) ($input['context_id'] ?? ''));
    $thread = mg_personal_agent_thread($pdo, $userId, mg_personal_agent_text($input['thread_id'] ?? '', 80), $context);
    $publicContext = mg_personal_agent_public_context($context);
    $aiContext = mg_personal_agent_ai_context($context);
    $userMessage = mg_personal_agent_store_message($pdo, $userId, $thread['internal_id'], 'user', $message, [], $publicContext);
    $dashboard = mg_personal_agent_dashboard($pdo, $userId);
    $knowledge = mg_personal_agent_knowledge($pdo, $userId, $message);
    $memory = array_slice($dashboard['memory'] ?? [], 0, 30);
    $history = mg_personal_agent_messages($pdo, $userId, $thread['internal_id'], 14);
    $model = mg_personal_agent_model($pdo, $userId, mg_personal_agent_text($input['model_id'] ?? '', 80));
    $result = null;
    $modelKey = '';

    if (mg_personal_agent_message_has_secret_request($message)) {
        $result = mg_personal_agent_fallback_v2($message, $publicContext, $dashboard, $knowledge);
    } elseif ($model) {
        try {
            $provider = $model;
            $provider['id'] = (int) $model['provider_id'];
            mg_ai_enforce_rate_limits($pdo, $provider, $model, $userId, null);
            $messages = [];
            foreach ($history as $item) {
                if (!in_array($item['role'], ['user', 'assistant'], true)) continue;
                $messages[] = ['role' => $item['role'], 'content' => mb_substr((string) $item['body'], 0, 4000)];
            }
            $contextPayload = [
                'selected_context' => $aiContext,
                'upcoming_dates' => array_slice($dashboard['upcoming_dates'] ?? [], 0, 30),
                'active_plans' => array_slice(array_values(array_filter($dashboard['plans'] ?? [], static fn(array $plan): bool => in_array($plan['status'], ['draft', 'planned', 'ready'], true))), 0, 30),
                'scheduled_reminders' => array_slice($dashboard['reminders'] ?? [], 0, 30),
                'agent_memory' => array_map(static fn(array $item): array => ['category' => $item['category'], 'title' => $item['title'], 'value' => $item['value']], $memory),
                'settings' => $dashboard['settings'] ?? [],
                'permission_scoped_knowledge' => $knowledge,
            ];
            $payload = [
                'model' => (string) $model['model_key'],
                'max_tokens' => max(700, min(2200, (int) ($model['max_output_tokens'] ?? 1600))),
                'temperature' => 0.25,
                'system' => mg_personal_agent_system_prompt_v2() . "\n\nPermission-scoped context JSON:\n" . mg_personal_agent_json_encode($contextPayload),
                'messages' => $messages,
            ];
            $response = mg_anthropic_messages($payload);
            $decoded = mg_anthropic_extract_json_object(mg_anthropic_text_from_response($response));
            $reply = mg_personal_agent_text($decoded['reply'] ?? '', 6000);
            $cards = mg_personal_agent_normalize_cards($decoded['cards'] ?? [], $publicContext);
            if ($reply === '') throw new RuntimeException('Claude returned an empty reply.');
            $result = ['reply' => $reply, 'cards' => $cards];
            $modelKey = (string) $model['model_key'];
            mg_ai_insert_usage_event($pdo, (int) $model['provider_id'], (int) $model['id'], $userId, null, 'completed', null, ['source' => 'personal_gifting_agent_v2']);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning', 'user_agent.ai_fallback', 'Personal agent AI request used the permission-safe fallback.', ['exception_type' => $error::class], $userId);
            }
        }
    }

    if (!$result) $result = mg_personal_agent_fallback_v2($message, $publicContext, $dashboard, $knowledge);
    $cards = mg_personal_agent_normalize_cards($result['cards'] ?? [], $publicContext);
    $assistant = mg_personal_agent_store_message($pdo, $userId, $thread['internal_id'], 'assistant', (string) $result['reply'], $cards, $publicContext, $modelKey);
    mg_audit('user_agent.chat_completed', 'user_agent_thread', [
        'thread_id' => $thread['id'],
        'model_key' => $modelKey ?: 'safe_fallback',
        'context_type' => $publicContext['type'],
        'account_context' => true,
        'marketplace_context' => true,
        'secret_request_blocked' => mg_personal_agent_message_has_secret_request($message),
    ], $userId);

    return [
        'thread' => ['id' => $thread['id'], 'title' => $thread['title']],
        'user_message' => $userMessage,
        'assistant_message' => $assistant,
        'context' => $publicContext,
        'used_ai' => $modelKey !== '',
        'model_key' => $modelKey,
        'knowledge_scope' => ['account' => true, 'marketplace' => true, 'merchant_secrets' => false],
    ];
}

<?php
declare(strict_types=1);

const MG_PERSONAL_AGENT_MERCHANT_OPPORTUNITY_PROMPT = 'I can also check these merchants for current local deals, free rewards, active campaigns, published products, entertainment, and experiences you have not already joined or saved. Would you like me to show the best options for personal use or for a gift?';

function mg_personal_agent_merchant_opportunity_explicit(string $message): bool
{
    $message = mb_strtolower(trim($message));
    $scope = mg_personal_agent_message_mentions($message, [
        'my inbox merchants', 'inbox merchants', 'merchants in my inbox', 'merchants from my inbox',
        'merchants from my gifts', 'gift merchants', 'these merchants', 'those merchants',
        'what else do they offer', 'anything else from them', 'other offers from them',
    ]);
    $intent = mg_personal_agent_message_mentions($message, [
        'merchant opportunities', 'merchant deals', 'local deals', 'best deals', 'show deals',
        'show offers', 'current offers', 'free product', 'free products', 'free reward', 'free rewards',
        'discount savings', 'discounted savings', 'discounts', 'campaigns', 'experiences',
        'entertainment', 'personal use', 'for myself', 'gift options', 'gift ideas',
    ]);
    return $scope && $intent;
}

function mg_personal_agent_merchant_opportunity_affirmative(string $message): bool
{
    $message = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $message) ?? $message));
    return in_array($message, [
        'yes', 'yes please', 'sure', 'please do', 'show me', 'show them', 'go ahead',
        'show me the deals', 'show me the offers', 'show me the best options',
    ], true);
}

function mg_personal_agent_previous_opportunity_prompt(PDO $pdo, int $userId, string $threadPublicId, string $currentAssistantId): bool
{
    if ($threadPublicId === '') return false;
    try {
        $stmt = $pdo->prepare("SELECT m.body
            FROM user_agent_messages m
            INNER JOIN user_agent_threads t ON t.id=m.thread_id AND t.owner_user_id=m.owner_user_id
            WHERE m.owner_user_id=? AND t.public_id=? AND t.cleared_at IS NULL
              AND m.role='assistant' AND m.public_id<>?
            ORDER BY m.id DESC LIMIT 1");
        $stmt->execute([$userId, $threadPublicId, $currentAssistantId]);
        $body = (string) ($stmt->fetchColumn() ?: '');
        return str_contains($body, 'Would you like me to show the best options for personal use or for a gift?');
    } catch (Throwable) {
        return false;
    }
}

function mg_personal_agent_inbox_merchant_ids(PDO $pdo, int $userId): array
{
    $ids = [];
    foreach (mg_personal_agent_account_gift_items($pdo, $userId, 'inbox', 24) as $item) {
        if (!is_array($item)) continue;
        $merchantId = (int) ($item['merchant_user_id'] ?? 0);
        if ($merchantId < 1) $merchantId = (int) ($item['sender_id'] ?? 0);
        if ($merchantId > 0) $ids[$merchantId] = $merchantId;
    }
    return array_values($ids);
}

function mg_personal_agent_opportunity_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function mg_personal_agent_opportunity_value(array $row): string
{
    $type = (string) ($row['value_type'] ?? '');
    if ($type === 'percent') {
        $percent = (float) ($row['value_percent'] ?? 0);
        return rtrim(rtrim(number_format($percent, 2), '0'), '.') . '% off';
    }
    $cents = (int) ($row['value_amount_cents'] ?? 0);
    if ($cents <= 0 && in_array((string) ($row['reward_type'] ?? ''), ['free_item', 'experience'], true)) return 'FREE';
    return mg_personal_agent_marketplace_money($cents, (string) ($row['currency'] ?? 'USD'));
}

function mg_personal_agent_opportunity_image(mixed $metadata, mixed $fallback = ''): string
{
    $decoded = [];
    if (is_array($metadata)) $decoded = $metadata;
    elseif (is_string($metadata) && trim($metadata) !== '') {
        $candidate = json_decode($metadata, true);
        if (is_array($candidate)) $decoded = $candidate;
    }
    $pack = is_array($decoded['media_pack'] ?? null) ? $decoded['media_pack'] : [];
    foreach (['reward_image_url', 'cover_image_url', 'image_url', 'thumbnail_url'] as $key) {
        $url = mg_personal_agent_account_gift_safe_url($decoded[$key] ?? $pack[$key] ?? '');
        if ($url !== '') return $url;
    }
    return mg_personal_agent_account_gift_safe_url($fallback);
}

function mg_personal_agent_merchant_product_opportunities(PDO $pdo, int $userId, array $merchantIds, int $limit = 5): array
{
    if ($merchantIds === [] || !mg_personal_agent_table_exists($pdo, 'catalog_products')) return [];
    $limit = max(1, min(12, $limit));
    $in = mg_personal_agent_opportunity_placeholders($merchantIds);
    $params = array_values($merchantIds);
    $params[] = $userId;
    $params[] = $userId;
    $sql = "SELECT cp.public_id,cp.slug,cp.product_type,cp.published_at,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,
        pp.slug merchant_slug,pp.display_name merchant_name,pp.location_label,pp.cover_url,pp.avatar_url,
        ms.slug storefront_slug,cover.public_id cover_asset_id,
        GROUP_CONCAT(DISTINCT CONCAT_WS('~',ml.name,COALESCE(ml.city,''),COALESCE(ml.region,'')) ORDER BY cpvl.is_primary DESC,ml.name ASC SEPARATOR '||') location_rows
        FROM catalog_products cp
        INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
        INNER JOIN catalog_product_version_locations cpvl ON cpvl.product_version_id=cpv.id AND cpvl.availability_status='available'
        INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
        INNER JOIN users u ON u.id=cp.merchant_user_id AND u.status='active'
        INNER JOIN public_profiles pp ON pp.user_id=cp.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        LEFT JOIN merchant_storefronts ms ON ms.merchant_user_id=cp.merchant_user_id AND ms.status='published'
        LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id AND pva.role='cover'
        LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id AND cover.status='ready'
        WHERE cp.merchant_user_id IN ({$in}) AND cp.status='published' AND cpv.version_status='published'
          AND (cpv.metadata_json IS NULL OR COALESCE(JSON_UNQUOTE(JSON_EXTRACT(cpv.metadata_json,'$.demo')),'false') NOT IN ('true','1'))
          AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=cp.merchant_user_id) OR (sb.blocking_user_id=cp.merchant_user_id AND sb.blocked_user_id=?))
        GROUP BY cp.id,cp.public_id,cp.slug,cp.product_type,cp.published_at,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,
          pp.slug,pp.display_name,pp.location_label,pp.cover_url,pp.avatar_url,ms.slug,cover.public_id
        ORDER BY cp.published_at DESC,cp.id DESC LIMIT {$limit}";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'user_agent.merchant_product_opportunities_unavailable', 'Inbox merchant products were unavailable.', ['exception_type'=>$error::class], $userId);
        return [];
    }
    $cards = [];
    foreach ($rows as $row) {
        $location = '';
        $first = explode('||', (string) ($row['location_rows'] ?? ''))[0] ?? '';
        if ($first !== '') {
            $parts = array_pad(explode('~', $first, 3), 3, '');
            $location = trim(implode(', ', array_filter([$parts[1], $parts[2]]))) ?: $parts[0];
        }
        $image = $row['cover_asset_id'] ? '/api/public/media.php?asset=' . rawurlencode((string) $row['cover_asset_id']) : mg_personal_agent_account_gift_safe_url($row['cover_url'] ?? $row['avatar_url'] ?? '');
        $cards[] = [
            'type'=>'marketplace_product','result_kind'=>'product','id'=>(string)$row['public_id'],
            'eyebrow'=>ucfirst(str_replace('_',' ',(string)$row['product_type'])),'title'=>(string)$row['title'],
            'body'=>mg_personal_agent_text($row['description'] ?? 'Published product or local experience from an Inbox merchant.',700),
            'image_url'=>$image,'image_alt'=>(string)$row['title'],
            'price'=>mg_personal_agent_marketplace_money((int)$row['unit_value_cents'],(string)$row['currency']),
            'url'=>'/product.php?p=' . rawurlencode((string)$row['slug']),'url_label'=>'Purchase',
            'secondary_url'=>'/profile.php?slug=' . rawurlencode((string)$row['merchant_slug']),'secondary_label'=>'View merchant',
            'merchant_name'=>(string)$row['merchant_name'],'purchase_available'=>true,
            'meta'=>array_values(array_filter([
                ['label'=>'Merchant','value'=>(string)$row['merchant_name']],
                $location !== '' ? ['label'=>'Location','value'=>$location] : null,
                ['label'=>'Use','value'=>'Personal or gift'],
            ])),
            'reason'=>'Available now from a merchant connected to your Inbox.','action'=>'open_marketplace_result','action_label'=>'Purchase','risk_level'=>'low','_score'=>70,
        ];
    }
    return $cards;
}

function mg_personal_agent_merchant_campaign_opportunities(PDO $pdo, int $userId, string $email, array $merchantIds, int $limit = 5): array
{
    if ($merchantIds === [] || !mg_personal_agent_table_exists($pdo, 'campaigns')) return [];
    $limit = max(1, min(12, $limit));
    $in = mg_personal_agent_opportunity_placeholders($merchantIds);
    $params = array_values($merchantIds);
    array_push($params, $userId, $email, $email, $userId, $email, $email, $userId, $userId);
    $sql = "SELECT c.public_id,c.public_slug,c.campaign_type,c.title,c.description,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.updated_at,
        rt.reward_type,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,
        pp.slug merchant_slug,pp.display_name merchant_name,pp.location_label,pp.cover_url,pp.avatar_url
        FROM campaigns c
        INNER JOIN users u ON u.id=c.merchant_user_id AND u.status='active'
        INNER JOIN public_profiles pp ON pp.user_id=c.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
        WHERE c.merchant_user_id IN ({$in}) AND c.status='active' AND c.agent_discoverable=1
          AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW())
          AND (c.quantity_limit IS NULL OR c.issued_count<c.quantity_limit)
          AND NOT EXISTS(SELECT 1 FROM campaign_contacts cc WHERE cc.campaign_id=c.id AND (cc.user_id=? OR (?<>'' AND LOWER(cc.email)=?)))
          AND NOT EXISTS(SELECT 1 FROM wallet_items wi LEFT JOIN campaign_contacts wcc ON wcc.id=wi.contact_id WHERE wi.campaign_id=c.id AND wi.status<>'cancelled' AND (wi.user_id=? OR (?<>'' AND LOWER(wcc.email)=?)))
          AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=c.merchant_user_id) OR (sb.blocking_user_id=c.merchant_user_id AND sb.blocked_user_id=?))
        ORDER BY CASE WHEN c.ends_at IS NULL THEN 1 ELSE 0 END,c.ends_at ASC,c.updated_at DESC LIMIT {$limit}";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'user_agent.merchant_campaign_opportunities_unavailable', 'Inbox merchant campaigns were unavailable.', ['exception_type'=>$error::class], $userId);
        return [];
    }
    $cards = [];
    foreach ($rows as $row) {
        $definition = mg_campaign_type_get((string) $row['campaign_type']) ?? [];
        if (empty($definition['public_enabled']) || !empty($definition['internal_only'])) continue;
        $path = mg_personal_agent_marketplace_internal_url($definition['public_path'] ?? '');
        $reference = trim((string) ($row['public_slug'] ?: $row['public_id']));
        if ($path === '' || $reference === '') continue;
        $progress = null;
        if ($row['quantity_limit'] !== null && (int)$row['quantity_limit'] > 0) $progress = max(0,min(100,(int)round(((int)$row['issued_count']/(int)$row['quantity_limit'])*100)));
        $cards[] = [
            'type'=>'marketplace_campaign','result_kind'=>'campaign','id'=>(string)$row['public_id'],
            'eyebrow'=>(string)($definition['label'] ?? mg_campaign_type_label((string)$row['campaign_type'])),'title'=>(string)$row['title'],
            'body'=>mg_personal_agent_text($row['description'] ?? 'Active merchant campaign with a current customer opportunity.',700),
            'image_url'=>mg_personal_agent_account_gift_safe_url($row['cover_url'] ?? $row['avatar_url'] ?? ''),'image_alt'=>(string)$row['merchant_name'],
            'price'=>$row['reward_type'] ? mg_personal_agent_opportunity_value($row) : '',
            'url'=>$path . '?campaign=' . rawurlencode($reference),'url_label'=>'Participate',
            'secondary_url'=>'/profile.php?slug=' . rawurlencode((string)$row['merchant_slug']),'secondary_label'=>'View merchant',
            'merchant_name'=>(string)$row['merchant_name'],'progress'=>$progress,
            'meta'=>array_values(array_filter([
                ['label'=>'Merchant','value'=>(string)$row['merchant_name']],
                (string)$row['location_label'] !== '' ? ['label'=>'Location','value'=>(string)$row['location_label']] : null,
                $row['ends_at'] ? ['label'=>'Ends','value'=>(string)$row['ends_at']] : null,
            ])),
            'reason'=>'You have not participated in this active campaign yet.','action'=>'open_marketplace_result','action_label'=>'Participate','risk_level'=>'low','_score'=>95,
        ];
    }
    return $cards;
}

function mg_personal_agent_merchant_reward_opportunities(PDO $pdo, int $userId, string $email, array $merchantIds, int $limit = 5): array
{
    if ($merchantIds === [] || !mg_personal_agent_table_exists($pdo, 'reward_templates')) return [];
    $limit = max(1, min(12, $limit));
    $in = mg_personal_agent_opportunity_placeholders($merchantIds);
    $params = array_values($merchantIds);
    array_push($params, $userId, $email, $email, $userId, $userId);
    $sql = "SELECT rt.public_id,rt.title,rt.description,rt.reward_type,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,
        rt.agent_summary,rt.agent_use_cases_json,rt.agent_add_to_wallet_allowed,rt.agent_gift_send_allowed,rt.metadata_json,rt.expires_at,rt.updated_at,
        pp.slug merchant_slug,pp.display_name merchant_name,pp.location_label,pp.cover_url,pp.avatar_url
        FROM reward_templates rt
        INNER JOIN users u ON u.id=rt.merchant_user_id AND u.status='active'
        INNER JOIN public_profiles pp ON pp.user_id=rt.merchant_user_id AND pp.status='active' AND pp.visibility IN ('public','unlisted')
        WHERE rt.merchant_user_id IN ({$in}) AND rt.status='active' AND rt.agent_discoverable=1
          AND (rt.agent_add_to_wallet_allowed=1 OR rt.agent_gift_send_allowed=1)
          AND (rt.expires_at IS NULL OR rt.expires_at>=NOW())
          AND NOT EXISTS(SELECT 1 FROM wallet_items wi LEFT JOIN campaign_contacts cc ON cc.id=wi.contact_id WHERE wi.reward_template_id=rt.id AND wi.status<>'cancelled' AND (wi.user_id=? OR (?<>'' AND LOWER(cc.email)=?)))
          AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=rt.merchant_user_id) OR (sb.blocking_user_id=rt.merchant_user_id AND sb.blocked_user_id=?))
        ORDER BY CASE rt.reward_type WHEN 'free_item' THEN 0 WHEN 'discount' THEN 1 WHEN 'experience' THEN 2 ELSE 3 END,rt.updated_at DESC LIMIT {$limit}";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'user_agent.merchant_reward_opportunities_unavailable', 'Inbox merchant rewards were unavailable.', ['exception_type'=>$error::class], $userId);
        return [];
    }
    $cards = [];
    foreach ($rows as $row) {
        $useCases = json_decode((string)($row['agent_use_cases_json'] ?? ''), true);
        $useCases = is_array($useCases) ? array_slice(array_map('strval',$useCases),0,3) : [];
        $type = (string)$row['reward_type'];
        $label = match($type) {
            'free_item'=>'Free reward','discount'=>'Discount','experience'=>'Experience','gift_card'=>'Gift offer',default=>'Local reward',
        };
        $score = match($type) {'free_item'=>100,'discount'=>92,'experience'=>88,'gift_card'=>82,default=>78};
        $cards[] = [
            'type'=>'marketplace_product','result_kind'=>'product','id'=>(string)$row['public_id'],
            'eyebrow'=>$label,'title'=>(string)$row['title'],
            'body'=>mg_personal_agent_text($row['agent_summary'] ?: $row['description'] ?: 'Agent-discoverable reward from an Inbox merchant.',700),
            'image_url'=>mg_personal_agent_opportunity_image($row['metadata_json'] ?? '', $row['cover_url'] ?? $row['avatar_url'] ?? ''),'image_alt'=>(string)$row['title'],
            'price'=>mg_personal_agent_opportunity_value($row),
            'url'=>'/offers.php?offer=' . rawurlencode((string)$row['public_id']),'url_label'=>'View offer',
            'secondary_url'=>'/profile.php?slug=' . rawurlencode((string)$row['merchant_slug']),'secondary_label'=>'View merchant',
            'merchant_name'=>(string)$row['merchant_name'],'purchase_available'=>false,
            'meta'=>array_values(array_filter([
                ['label'=>'Merchant','value'=>(string)$row['merchant_name']],
                (string)$row['location_label'] !== '' ? ['label'=>'Location','value'=>(string)$row['location_label']] : null,
                $useCases !== [] ? ['label'=>'Best for','value'=>implode(', ',$useCases)] : ['label'=>'Use','value'=>'Personal or gift'],
            ])),
            'reason'=>'This reward is available and is not already in your wallet.','action'=>'open_marketplace_result','action_label'=>'View offer','risk_level'=>'low','_score'=>$score,
        ];
    }
    return $cards;
}

function mg_personal_agent_merchant_opportunity_cards(PDO $pdo, int $userId, int $limit = 8): array
{
    $merchantIds = mg_personal_agent_inbox_merchant_ids($pdo, $userId);
    if ($merchantIds === []) return [];
    $email = mg_personal_agent_account_gift_email($pdo, $userId);
    $cards = array_merge(
        mg_personal_agent_merchant_campaign_opportunities($pdo, $userId, $email, $merchantIds, 4),
        mg_personal_agent_merchant_reward_opportunities($pdo, $userId, $email, $merchantIds, 4),
        mg_personal_agent_merchant_product_opportunities($pdo, $userId, $merchantIds, 5)
    );
    usort($cards, static fn(array $a, array $b): int => ((int)($b['_score'] ?? 0)) <=> ((int)($a['_score'] ?? 0)));
    $cards = array_slice($cards, 0, max(1,min(12,$limit)));
    foreach ($cards as &$card) unset($card['_score']);
    unset($card);
    return $cards;
}

function mg_personal_agent_persist_opportunity_response(PDO $pdo, int $userId, string $assistantId, string $body, array $cards): void
{
    if ($assistantId === '') return;
    try {
        $stmt = $pdo->prepare("UPDATE user_agent_messages SET body=?,cards_json=? WHERE owner_user_id=? AND public_id=? AND role='assistant'");
        $stmt->execute([$body, $cards !== [] ? mg_personal_agent_json_encode($cards) : null, $userId, $assistantId]);
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) mg_security_log('warning', 'user_agent.merchant_opportunities_persist_failed', 'Merchant opportunity response could not be persisted.', ['exception_type'=>$error::class], $userId);
    }
}

function mg_personal_agent_chat_with_merchant_opportunities(PDO $pdo, int $userId, array $input): array
{
    $result = mg_personal_agent_chat_with_account_gift_response($pdo, $userId, $input);
    $message = mg_personal_agent_text($input['message'] ?? '', 2000);
    $assistantId = (string)($result['assistant_message']['id'] ?? '');
    $threadId = (string)($result['thread']['id'] ?? $input['thread_id'] ?? '');
    $requested = mg_personal_agent_merchant_opportunity_explicit($message)
        || (mg_personal_agent_merchant_opportunity_affirmative($message) && mg_personal_agent_previous_opportunity_prompt($pdo, $userId, $threadId, $assistantId));

    if ($requested) {
        $cards = mg_personal_agent_merchant_opportunity_cards($pdo, $userId, 8);
        $body = $cards === []
            ? 'I did not find any new customer opportunities from the merchants currently represented in your Inbox. Campaigns already joined and rewards already in your wallet were excluded.'
            : 'I found ' . count($cards) . ' current opportunities from merchants connected to your Inbox. I excluded campaigns you already joined and rewards already in your wallet, then ranked the remaining local deals for savings, personal entertainment or experiences, and gift potential.';
        mg_personal_agent_persist_opportunity_response($pdo, $userId, $assistantId, $body, $cards);
        $result['assistant_message']['body'] = $body;
        $result['assistant_message']['cards'] = $cards;
        $result['merchant_opportunity_result_count'] = count($cards);
        $result['merchant_opportunity_source'] = 'inbox_merchants';
        return $result;
    }

    if (($result['account_gift_folder'] ?? '') === 'inbox' && (int)($result['account_gift_result_count'] ?? 0) > 0) {
        $available = mg_personal_agent_merchant_opportunity_cards($pdo, $userId, 8);
        if ($available !== []) {
            $body = rtrim((string)($result['assistant_message']['body'] ?? '')) . ' ' . MG_PERSONAL_AGENT_MERCHANT_OPPORTUNITY_PROMPT;
            $cards = is_array($result['assistant_message']['cards'] ?? null) ? $result['assistant_message']['cards'] : [];
            mg_personal_agent_persist_opportunity_response($pdo, $userId, $assistantId, $body, $cards);
            $result['assistant_message']['body'] = $body;
            $result['merchant_opportunity_available_count'] = count($available);
            $result['merchant_opportunity_follow_up'] = true;
        }
    }
    return $result;
}

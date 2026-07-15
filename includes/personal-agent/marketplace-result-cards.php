<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/campaign-types.php';

function mg_personal_agent_marketplace_result_intent(string $message): string
{
    $message = mb_strtolower($message);
    if (mg_personal_agent_message_mentions($message, [
        'campaign', 'campaigns', 'contest', 'giveaway', 'promotion', 'promotions',
        'reward offer', 'reward offers', 'birthday club', 'vip club', 'referral reward',
    ])) return 'campaign';

    if (mg_personal_agent_message_mentions($message, [
        'product', 'products', 'item', 'items', 'gift', 'gifts', 'available', 'availability',
        'buy', 'purchase', 'experience', 'experiences', 'deal', 'deals', 'offerings',
    ])) return 'product';

    if (mg_personal_agent_message_mentions($message, [
        'merchant', 'merchants', 'business', 'businesses', 'store', 'stores', 'shop', 'shops',
        'restaurant', 'restaurants', 'cafe', 'cafes', 'bar', 'bars', 'artist', 'artists',
        'creator', 'creators', 'marketplace',
    ])) return 'merchant';

    return '';
}

function mg_personal_agent_marketplace_location(string $message): string
{
    $message = mb_strtolower($message);
    $states = [
        'alabama'=>'al','alaska'=>'ak','arizona'=>'az','arkansas'=>'ar','california'=>'ca','colorado'=>'co',
        'connecticut'=>'ct','delaware'=>'de','florida'=>'fl','georgia'=>'ga','hawaii'=>'hi','idaho'=>'id',
        'illinois'=>'il','indiana'=>'in','iowa'=>'ia','kansas'=>'ks','kentucky'=>'ky','louisiana'=>'la',
        'maine'=>'me','maryland'=>'md','massachusetts'=>'ma','michigan'=>'mi','minnesota'=>'mn',
        'mississippi'=>'ms','missouri'=>'mo','montana'=>'mt','nebraska'=>'ne','nevada'=>'nv',
        'new hampshire'=>'nh','new jersey'=>'nj','new mexico'=>'nm','new york'=>'ny',
        'north carolina'=>'nc','north dakota'=>'nd','ohio'=>'oh','oklahoma'=>'ok','oregon'=>'or',
        'pennsylvania'=>'pa','rhode island'=>'ri','south carolina'=>'sc','south dakota'=>'sd',
        'tennessee'=>'tn','texas'=>'tx','utah'=>'ut','vermont'=>'vt','virginia'=>'va',
        'washington'=>'wa','west virginia'=>'wv','wisconsin'=>'wi','wyoming'=>'wy',
        'district of columbia'=>'dc','washington dc'=>'dc',
    ];
    foreach ($states as $name => $code) {
        if (preg_match('/\b' . preg_quote($name, '/') . '\b/u', $message) === 1) return $code;
    }

    if (preg_match('/\b(?:in|near|around)\s+([a-z][a-z .\'-]{1,40})(?:[?.,!]|$)/iu', $message, $match) === 1) {
        $location = trim((string) ($match[1] ?? ''));
        $blocked = ['the marketplace','microgifter','the area','my area','the city','town','local'];
        if ($location !== '' && !in_array($location, $blocked, true)) return mg_personal_agent_text($location, 60);
    }
    return '';
}

function mg_personal_agent_marketplace_result_filters(string $message): array
{
    $category = mg_personal_agent_marketplace_category($message);
    $lower = mb_strtolower($message);
    if ($category === 'retail' && str_contains($lower, 'product') && !mg_personal_agent_message_mentions($lower, ['retail','shop','shopping','store'])) {
        $category = '';
    }
    return [
        'category' => $category,
        'location' => mg_personal_agent_marketplace_location($message),
    ];
}

function mg_personal_agent_marketplace_internal_url(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '' || !str_starts_with($value, '/') || str_starts_with($value, '//')) return '';
    if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) return '';
    return mb_substr($value, 0, 500);
}

function mg_personal_agent_marketplace_money(int $cents, string $currency): string
{
    $currency = strtoupper(trim($currency)) ?: 'USD';
    $amount = number_format(max(0, $cents) / 100, 2);
    return $currency === 'USD' ? '$' . $amount : $currency . ' ' . $amount;
}

function mg_personal_agent_marketplace_merchants(PDO $pdo, int $userId, array $filters, int $limit = 6): array
{
    $input = ['type'=>'merchant','sort'=>'active','limit'=>max(1,min(12,$limit))];
    if (($filters['category'] ?? '') !== '') $input['category'] = (string) $filters['category'];
    if (($filters['location'] ?? '') !== '') $input['location'] = (string) $filters['location'];
    $payload = mg_profile_discovery_search($pdo, $input, $userId);
    return array_map(static fn(array $merchant): array => [
        'id'=>(string)($merchant['id'] ?? ''),
        'name'=>(string)($merchant['display_name'] ?? ''),
        'business_name'=>(string)($merchant['business_name'] ?? $merchant['display_name'] ?? ''),
        'headline'=>mg_personal_agent_text($merchant['headline'] ?? '',500),
        'location'=>(string)($merchant['location'] ?? ''),
        'profile_type'=>(string)($merchant['profile_type'] ?? 'merchant'),
        'published_products'=>(int)($merchant['published_products'] ?? 0),
        'published_campaigns'=>(int)($merchant['published_campaigns'] ?? 0),
        'avatar_url'=>(string)($merchant['avatar_url'] ?? ''),
        'cover_url'=>(string)($merchant['cover_url'] ?? ''),
        'url'=>mg_personal_agent_marketplace_internal_url($merchant['url'] ?? ''),
    ], array_slice($payload['items'] ?? [],0,max(1,min(12,$limit))));
}

function mg_personal_agent_marketplace_products(PDO $pdo, int $userId, array $filters, int $limit = 6): array
{
    $input = ['type'=>'merchant','sort'=>'active','product_limit'=>max(1,min(12,$limit))];
    if (($filters['category'] ?? '') !== '') $input['category'] = (string) $filters['category'];
    if (($filters['location'] ?? '') !== '') $input['location'] = (string) $filters['location'];
    $payload = mg_product_discovery_search($pdo, $input, $userId);
    return array_map(static function(array $product): array {
        $locations = is_array($product['locations'] ?? null) ? array_slice($product['locations'],0,6) : [];
        $first = is_array($locations[0] ?? null) ? $locations[0] : [];
        return [
            'id'=>(string)($product['id'] ?? ''),
            'title'=>(string)($product['title'] ?? ''),
            'description'=>mg_personal_agent_text($product['description'] ?? '',700),
            'type'=>(string)($product['product_type'] ?? ''),
            'value_cents'=>(int)($product['value_cents'] ?? 0),
            'currency'=>(string)($product['currency'] ?? 'USD'),
            'cover_url'=>(string)($product['cover_url'] ?? ''),
            'url'=>mg_personal_agent_marketplace_internal_url($product['url'] ?? ''),
            'purchase_available'=>(bool)($product['purchase_available'] ?? false),
            'location'=>trim(implode(', ',array_filter([(string)($first['city'] ?? ''),(string)($first['region'] ?? '')]))),
            'merchant'=>[
                'name'=>(string)($product['merchant']['name'] ?? ''),
                'url'=>mg_personal_agent_marketplace_internal_url($product['merchant']['url'] ?? ''),
                'store_url'=>mg_personal_agent_marketplace_internal_url($product['merchant']['store_url'] ?? ''),
            ],
        ];
    },array_slice($payload['items'] ?? [],0,max(1,min(12,$limit))));
}

function mg_personal_agent_marketplace_campaigns(PDO $pdo, int $userId, array $filters, int $limit = 6): array
{
    if (!mg_personal_agent_table_exists($pdo,'campaigns')) return [];
    $where = [
        "u.status='active'",
        "pp.status='active'",
        "pp.visibility IN ('public','unlisted')",
        "c.status='active'",
        'c.agent_discoverable=1',
    ];
    $params = [];
    if (($filters['location'] ?? '') !== '') {
        $where[] = "LOWER(COALESCE(pp.location_label,'')) LIKE ?";
        $params[] = '%' . mb_strtolower((string)$filters['location']) . '%';
    }
    $where[] = 'NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=c.merchant_user_id) OR (sb.blocking_user_id=c.merchant_user_id AND sb.blocked_user_id=?))';
    $params[] = $userId;
    $params[] = $userId;
    $limit = max(1,min(12,$limit));
    $sql = "SELECT c.public_id,c.public_slug,c.campaign_type,c.title,c.description,c.starts_at,c.ends_at,c.quantity_limit,c.issued_count,c.updated_at,
        pp.slug merchant_slug,pp.display_name merchant_name,pp.avatar_url,pp.cover_url,pp.location_label,
        COALESCE((SELECT NULLIF(ms.display_name,'') FROM merchant_storefronts ms WHERE ms.merchant_user_id=c.merchant_user_id AND ms.status='published' LIMIT 1),pp.display_name) business_name
        FROM campaigns c
        INNER JOIN users u ON u.id=c.merchant_user_id
        INNER JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
        WHERE " . implode(' AND ',$where) . "
        ORDER BY COALESCE(c.starts_at,c.updated_at) DESC,c.public_id DESC LIMIT {$limit}";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $error) {
        if (function_exists('mg_security_log')) {
            mg_security_log('warning','user_agent.marketplace_campaign_cards_unavailable','Public campaign cards were unavailable.',['exception_type'=>$error::class],$userId);
        }
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        $type = (string)($row['campaign_type'] ?? '');
        $definition = mg_campaign_type_get($type) ?? [];
        if (empty($definition['public_enabled']) || !empty($definition['internal_only'])) continue;
        $path = mg_personal_agent_marketplace_internal_url($definition['public_path'] ?? '');
        $reference = trim((string)($row['public_slug'] ?: $row['public_id'] ?? ''));
        if ($path === '' || $reference === '') continue;
        $limitValue = $row['quantity_limit'] !== null ? (int)$row['quantity_limit'] : null;
        $issued = (int)($row['issued_count'] ?? 0);
        $progress = $limitValue && $limitValue > 0 ? max(0,min(100,(int)round(($issued/$limitValue)*100))) : null;
        $items[] = [
            'id'=>(string)($row['public_id'] ?? ''),
            'title'=>(string)($row['title'] ?? 'Campaign'),
            'description'=>mg_personal_agent_text($row['description'] ?? '',700),
            'type'=>$type,
            'type_label'=>(string)($definition['label'] ?? mg_campaign_type_label($type)),
            'url'=>$path . '?campaign=' . rawurlencode($reference),
            'starts_at'=>$row['starts_at'] ?: null,
            'ends_at'=>$row['ends_at'] ?: null,
            'issued_count'=>$issued,
            'quantity_limit'=>$limitValue,
            'progress'=>$progress,
            'merchant'=>[
                'name'=>(string)($row['business_name'] ?? $row['merchant_name'] ?? ''),
                'profile_name'=>(string)($row['merchant_name'] ?? ''),
                'location'=>(string)($row['location_label'] ?? ''),
                'url'=>'/profile.php?slug=' . rawurlencode((string)($row['merchant_slug'] ?? '')),
                'image_url'=>(string)(mg_public_profile_safe_url($row['cover_url'] ?? null,true) ?? mg_public_profile_safe_url($row['avatar_url'] ?? null,true) ?? ''),
            ],
        ];
    }
    return $items;
}

function mg_personal_agent_marketplace_result_cards(PDO $pdo, int $userId, string $message): array
{
    $intent = mg_personal_agent_marketplace_result_intent($message);
    if ($intent === '') return [];
    $filters = mg_personal_agent_marketplace_result_filters($message);
    $cards = [];

    if ($intent === 'merchant') {
        foreach (mg_personal_agent_marketplace_merchants($pdo,$userId,$filters,6) as $merchant) {
            if ($merchant['url'] === '') continue;
            $cards[] = [
                'type'=>'marketplace_merchant','result_kind'=>'merchant','id'=>$merchant['id'],
                'eyebrow'=>'Local merchant','title'=>$merchant['business_name'] ?: $merchant['name'],
                'body'=>$merchant['headline'] ?: ('View ' . ($merchant['name'] ?: 'this merchant') . ' on Microgifter.'),
                'image_url'=>$merchant['cover_url'] ?: $merchant['avatar_url'],'image_alt'=>$merchant['business_name'] ?: $merchant['name'],
                'url'=>$merchant['url'],'url_label'=>'View merchant','secondary_url'=>'','secondary_label'=>'',
                'meta'=>array_values(array_filter([
                    $merchant['location'] !== '' ? ['label'=>'Location','value'=>$merchant['location']] : null,
                    ['label'=>'Products','value'=>(string)$merchant['published_products']],
                    ['label'=>'Campaigns','value'=>(string)$merchant['published_campaigns']],
                ])),
                'action'=>'open_marketplace_result','action_label'=>'View merchant','risk_level'=>'low',
            ];
        }
    } elseif ($intent === 'product') {
        foreach (mg_personal_agent_marketplace_products($pdo,$userId,$filters,6) as $product) {
            if ($product['url'] === '') continue;
            $cards[] = [
                'type'=>'marketplace_product','result_kind'=>'product','id'=>$product['id'],
                'eyebrow'=>$product['type'] !== '' ? ucfirst(str_replace('_',' ',$product['type'])) : 'Product',
                'title'=>$product['title'],'body'=>$product['description'] ?: 'Published Microgifter marketplace product.',
                'image_url'=>$product['cover_url'],'image_alt'=>$product['title'],
                'price'=>mg_personal_agent_marketplace_money($product['value_cents'],$product['currency']),
                'url'=>$product['url'],'url_label'=>'View product',
                'secondary_url'=>$product['merchant']['url'] ?: $product['merchant']['store_url'],'secondary_label'=>'View merchant',
                'merchant_name'=>$product['merchant']['name'],
                'meta'=>array_values(array_filter([
                    $product['merchant']['name'] !== '' ? ['label'=>'Merchant','value'=>$product['merchant']['name']] : null,
                    $product['location'] !== '' ? ['label'=>'Location','value'=>$product['location']] : null,
                    ['label'=>'Availability','value'=>$product['purchase_available'] ? 'Available' : 'View details'],
                ])),
                'action'=>'open_marketplace_result','action_label'=>'View product','risk_level'=>'low',
            ];
        }
    } else {
        foreach (mg_personal_agent_marketplace_campaigns($pdo,$userId,$filters,6) as $campaign) {
            if ($campaign['url'] === '') continue;
            $cards[] = [
                'type'=>'marketplace_campaign','result_kind'=>'campaign','id'=>$campaign['id'],
                'eyebrow'=>$campaign['type_label'],'title'=>$campaign['title'],
                'body'=>$campaign['description'] ?: 'Active public Microgifter campaign.',
                'image_url'=>$campaign['merchant']['image_url'],'image_alt'=>$campaign['merchant']['name'],
                'url'=>$campaign['url'],'url_label'=>'View campaign',
                'secondary_url'=>$campaign['merchant']['url'],'secondary_label'=>'View merchant',
                'merchant_name'=>$campaign['merchant']['name'],
                'progress'=>$campaign['progress'],
                'meta'=>array_values(array_filter([
                    $campaign['merchant']['name'] !== '' ? ['label'=>'Merchant','value'=>$campaign['merchant']['name']] : null,
                    $campaign['merchant']['location'] !== '' ? ['label'=>'Location','value'=>$campaign['merchant']['location']] : null,
                    $campaign['ends_at'] ? ['label'=>'Ends','value'=>(string)$campaign['ends_at']] : null,
                ])),
                'action'=>'open_marketplace_result','action_label'=>'View campaign','risk_level'=>'low',
            ];
        }
    }
    return array_slice($cards,0,6);
}

function mg_personal_agent_chat_with_marketplace_cards(PDO $pdo, int $userId, array $input): array
{
    $result = mg_personal_agent_chat_with_thread_title($pdo,$userId,$input);
    $message = mg_personal_agent_text($input['message'] ?? '',2000);
    $marketplaceCards = mg_personal_agent_marketplace_result_cards($pdo,$userId,$message);
    if ($marketplaceCards === []) return $result;

    $existing = is_array($result['assistant_message']['cards'] ?? null) ? $result['assistant_message']['cards'] : [];
    $existing = array_values(array_filter($existing,static fn(array $card): bool => !str_starts_with((string)($card['type'] ?? ''),'marketplace_')));
    $cards = array_slice(array_merge($marketplaceCards,$existing),0,10);
    $assistantId = (string)($result['assistant_message']['id'] ?? '');
    if ($assistantId !== '') {
        try {
            $stmt = $pdo->prepare("UPDATE user_agent_messages SET cards_json=? WHERE owner_user_id=? AND public_id=? AND role='assistant'");
            $stmt->execute([mg_personal_agent_json_encode($cards),$userId,$assistantId]);
        } catch (Throwable $error) {
            if (function_exists('mg_security_log')) {
                mg_security_log('warning','user_agent.marketplace_cards_persist_failed','Marketplace result cards could not be persisted.',['exception_type'=>$error::class],$userId);
            }
        }
    }
    $result['assistant_message']['cards'] = $cards;
    $result['marketplace_result_kind'] = mg_personal_agent_marketplace_result_intent($message);
    $result['marketplace_result_count'] = count($marketplaceCards);
    return $result;
}

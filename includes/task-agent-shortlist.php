<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/profiles/_product_discovery.php';
require_once __DIR__ . '/public-product-foundation.php';

function mg_task_agent_shortlist_schema_ready(PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='multi_agent_shortlist_items'");
        $stmt->execute();
        return (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        return false;
    }
}

function mg_task_agent_shortlist_require_schema(PDO $pdo): void
{
    if (!mg_task_agent_shortlist_schema_ready($pdo)) {
        throw new RuntimeException('Task Agent Phase 3.1 shortlist migration is required.');
    }
}

function mg_task_agent_shortlist_text(mixed $value, int $limit = 255): string
{
    return trim(mb_substr((string) $value, 0, max(1, $limit)));
}

function mg_task_agent_shortlist_recipient_context(array $value): array
{
    $allowed = ['contact_id','name','relationship','occasion','target_date','location'];
    $clean = [];
    foreach ($allowed as $key) {
        $text = mg_task_agent_shortlist_text($value[$key] ?? '', $key === 'contact_id' ? 80 : 190);
        if ($text !== '') $clean[$key] = $text;
    }
    foreach (['budget_min','budget_max'] as $key) {
        if (isset($value[$key]) && is_numeric($value[$key])) $clean[$key] = max(0, (float) $value[$key]);
    }
    return $clean;
}

function mg_task_agent_shortlist_product_record(PDO $pdo, int $userId, string $productPublicId): array
{
    $productPublicId = mg_task_agent_shortlist_text($productPublicId, 80);
    if ($productPublicId === '') throw new InvalidArgumentException('A published product is required.');

    $stmt = $pdo->prepare("SELECT cp.id product_id,cp.current_version_id product_version_id,cp.public_id
        FROM catalog_products cp
        INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published'
        WHERE cp.public_id=? AND cp.status='published'
          AND EXISTS(SELECT 1 FROM catalog_product_version_locations cpvl
            INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
            WHERE cpvl.product_version_id=cpv.id AND cpvl.availability_status='available')
          AND NOT EXISTS(SELECT 1 FROM social_blocks sb
            WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=cp.merchant_user_id)
               OR (sb.blocking_user_id=cp.merchant_user_id AND sb.blocked_user_id=?))
        LIMIT 1");
    $stmt->execute([$productPublicId,$userId,$userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$record) throw new RuntimeException('This product is no longer available to shortlist.');

    $record['product'] = mg_public_product_load($pdo, $productPublicId, null);
    return $record;
}

function mg_task_agent_shortlist_product_projection(array $product): array
{
    $cover = $product['media_by_role']['cover']['url'] ?? null;
    if (!$cover && !empty($product['assets'][0]['url'])) $cover = $product['assets'][0]['url'];
    $locations = [];
    foreach (array_slice(is_array($product['locations'] ?? null) ? $product['locations'] : [], 0, 4) as $location) {
        $label = trim(implode(', ', array_filter([
            mg_task_agent_shortlist_text($location['city'] ?? '', 100),
            mg_task_agent_shortlist_text($location['region'] ?? '', 100),
        ])));
        if ($label !== '') $locations[] = $label;
    }
    return [
        'id'=>(string)($product['public_id'] ?? ''),
        'version_id'=>(string)($product['version_id'] ?? ''),
        'title'=>mg_task_agent_shortlist_text($product['title'] ?? 'Published product',190),
        'description'=>mg_task_agent_shortlist_text($product['description'] ?? '',800),
        'product_type'=>mg_task_agent_shortlist_text($product['product_type'] ?? 'product',64),
        'value_cents'=>(int)($product['unit_value_cents'] ?? 0),
        'currency'=>mg_task_agent_shortlist_text($product['currency'] ?? 'USD',3) ?: 'USD',
        'url'=>mg_public_product_safe_url($product['public_url'] ?? null) ?? '',
        'cover_url'=>mg_public_product_safe_url($cover) ?? '',
        'merchant'=>[
            'name'=>mg_task_agent_shortlist_text($product['merchant_name'] ?? $product['merchant']['name'] ?? 'Local merchant',190),
            'url'=>mg_public_product_safe_url($product['merchant']['profile_url'] ?? null) ?? '',
            'store_url'=>mg_public_product_safe_url($product['merchant']['storefront_url'] ?? null) ?? '',
        ],
        'locations'=>array_values(array_unique($locations)),
        'purchase_available'=>!empty($product['is_purchasable']),
    ];
}

function mg_task_agent_shortlist_product_card(array $product, array $recipientContext = [], bool $shortlisted = false, string $shortlistId = ''): array
{
    $merchant = (string)($product['merchant']['name'] ?? 'Local merchant');
    $location = implode(' · ', array_slice($product['locations'] ?? [], 0, 2));
    $body = trim((string)($product['description'] ?? ''));
    if ($body === '') $body = 'Published Microgifter product from '.$merchant.'.';
    return [
        'type'=>'marketplace_product',
        'title'=>(string)($product['title'] ?? 'Local gift'),
        'body'=>$body,
        'price_cents'=>(int)($product['value_cents'] ?? 0),
        'currency'=>(string)($product['currency'] ?? 'USD'),
        'image_url'=>(string)($product['cover_url'] ?? ''),
        'merchant_name'=>$merchant,
        'location'=>$location,
        'url'=>(string)($product['url'] ?? ''),
        'action'=>$shortlisted ? 'remove_shortlist' : 'shortlist_product',
        'action_label'=>$shortlisted ? 'Remove from shortlist' : 'Shortlist',
        'review_payload'=>$shortlisted
            ? ['shortlist_id'=>$shortlistId]
            : ['product_id'=>(string)($product['id'] ?? ''),'recipient_context'=>mg_task_agent_shortlist_recipient_context($recipientContext)],
        'risk_level'=>'low',
    ];
}

function mg_task_agent_shortlist_list(PDO $pdo, int $userId, int $agentId, int $limit = 20): array
{
    if (!mg_task_agent_shortlist_schema_ready($pdo)) return [];
    $stmt = $pdo->prepare("SELECT s.public_id shortlist_id,s.discovery_reason,s.recipient_context_json,s.status,
        cp.public_id product_public_id
        FROM multi_agent_shortlist_items s
        INNER JOIN catalog_products cp ON cp.id=s.product_id AND cp.current_version_id=s.product_version_id AND cp.status='published'
        INNER JOIN catalog_product_versions cpv ON cpv.id=s.product_version_id AND cpv.version_status='published'
        WHERE s.owner_user_id=? AND s.agent_id=? AND s.status IN ('active','selected')
          AND EXISTS(SELECT 1 FROM catalog_product_version_locations cpvl
            INNER JOIN merchant_locations ml ON ml.id=cpvl.merchant_location_id AND ml.status='active'
            WHERE cpvl.product_version_id=s.product_version_id AND cpvl.availability_status='available')
        ORDER BY s.updated_at DESC,s.id DESC LIMIT ".max(1,min(50,$limit)));
    $stmt->execute([$userId,$agentId]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        try {
            $product = mg_task_agent_shortlist_product_projection(mg_public_product_load($pdo,(string)$row['product_public_id'],null));
        } catch (Throwable) {
            continue;
        }
        $context = json_decode((string)($row['recipient_context_json'] ?? ''),true);
        $items[] = [
            'id'=>(string)$row['shortlist_id'],
            'status'=>(string)$row['status'],
            'reason'=>(string)($row['discovery_reason'] ?? ''),
            'recipient_context'=>is_array($context)?mg_task_agent_shortlist_recipient_context($context):[],
            'product'=>$product,
        ];
    }
    return $items;
}

function mg_task_agent_shortlist_add(PDO $pdo, int $userId, int $agentId, array $input): array
{
    mg_task_agent_shortlist_require_schema($pdo);
    $record = mg_task_agent_shortlist_product_record($pdo,$userId,(string)($input['product_id'] ?? ''));
    $context = mg_task_agent_shortlist_recipient_context(is_array($input['recipient_context'] ?? null)?$input['recipient_context']:[]);
    $reason = mg_task_agent_shortlist_text($input['reason'] ?? 'Added from deterministic agent discovery.',255);
    $publicId = mg_public_uuid();
    $pdo->prepare("INSERT INTO multi_agent_shortlist_items
        (public_id,agent_id,owner_user_id,product_id,product_version_id,recipient_context_json,discovery_reason,status,created_at,updated_at)
        VALUES (?,?,?,?,?,?,?,'active',NOW(),NOW())
        ON DUPLICATE KEY UPDATE recipient_context_json=VALUES(recipient_context_json),discovery_reason=VALUES(discovery_reason),status='active',selected_at=NULL,updated_at=NOW()")
        ->execute([$publicId,$agentId,$userId,(int)$record['product_id'],(int)$record['product_version_id'],$context?json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,$reason]);
    mg_audit('multi_agent.shortlist_added','agent',['agent_id'=>$agentId,'product_id'=>(string)$record['public_id'],'used_ai'=>false],$userId);
    foreach (mg_task_agent_shortlist_list($pdo,$userId,$agentId,50) as $item) {
        if (($item['product']['id'] ?? '') === (string)$record['public_id']) return $item;
    }
    throw new RuntimeException('Unable to load the shortlisted product.');
}

function mg_task_agent_shortlist_remove(PDO $pdo, int $userId, int $agentId, string $shortlistId): void
{
    mg_task_agent_shortlist_require_schema($pdo);
    $shortlistId = mg_task_agent_shortlist_text($shortlistId,80);
    $stmt = $pdo->prepare("UPDATE multi_agent_shortlist_items SET status='removed',selected_at=NULL,updated_at=NOW()
        WHERE public_id=? AND owner_user_id=? AND agent_id=? AND status IN ('active','selected')");
    $stmt->execute([$shortlistId,$userId,$agentId]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Shortlist item not found.');
    mg_audit('multi_agent.shortlist_removed','agent',['agent_id'=>$agentId,'shortlist_id'=>$shortlistId,'used_ai'=>false],$userId);
}

function mg_task_agent_shortlist_filters(array $input): array
{
    $filters = [
        'q'=>mg_task_agent_shortlist_text($input['q'] ?? '',100),
        'type'=>'merchant',
        'location'=>mg_task_agent_shortlist_text($input['location'] ?? '',100),
        'category'=>mg_task_agent_shortlist_text($input['category'] ?? '',60),
        'product_limit'=>max(1,min(24,(int)($input['limit'] ?? 12))),
    ];
    foreach (['budget_min','budget_max'] as $key) {
        $filters[$key] = isset($input[$key]) && is_numeric($input[$key]) ? max(0,(float)$input[$key]) : null;
    }
    $filters['recipient_context'] = mg_task_agent_shortlist_recipient_context(is_array($input['recipient_context'] ?? null)?$input['recipient_context']:[]);
    return $filters;
}

function mg_task_agent_discover_products(PDO $pdo, int $userId, int $agentId, array $input): array
{
    $filters = mg_task_agent_shortlist_filters($input);
    $query = array_intersect_key($filters,array_flip(['q','type','location','category','product_limit']));
    $result = mg_product_discovery_search($pdo,$query,$userId);
    $items = is_array($result['items'] ?? null)?$result['items']:[];
    if (!$items && $filters['q'] !== '' && !empty($input['allow_broad_fallback'])) {
        $query['q'] = '';
        $result = mg_product_discovery_search($pdo,$query,$userId);
        $items = is_array($result['items'] ?? null)?$result['items']:[];
    }
    $minCents = $filters['budget_min'] !== null ? (int)round($filters['budget_min']*100) : null;
    $maxCents = $filters['budget_max'] !== null ? (int)round($filters['budget_max']*100) : null;
    $items = array_values(array_filter($items,static function(array $item) use ($minCents,$maxCents): bool {
        $value = (int)($item['value_cents'] ?? 0);
        if ($minCents !== null && $value < $minCents) return false;
        if ($maxCents !== null && $value > $maxCents) return false;
        return true;
    }));
    $shortlisted = [];
    foreach (mg_task_agent_shortlist_list($pdo,$userId,$agentId,50) as $item) $shortlisted[(string)($item['product']['id'] ?? '')] = $item;
    $cards = [];
    foreach (array_slice($items,0,12) as $item) {
        $projection = [
            'id'=>(string)($item['id'] ?? ''),'version_id'=>(string)($item['version_id'] ?? ''),'title'=>(string)($item['title'] ?? ''),
            'description'=>(string)($item['description'] ?? ''),'product_type'=>(string)($item['product_type'] ?? 'product'),
            'value_cents'=>(int)($item['value_cents'] ?? 0),'currency'=>(string)($item['currency'] ?? 'USD'),'url'=>(string)($item['url'] ?? ''),
            'cover_url'=>(string)($item['cover_url'] ?? ''),'merchant'=>is_array($item['merchant'] ?? null)?$item['merchant']:[],
            'locations'=>array_values(array_filter(array_map(static fn(array $location): string=>trim(implode(', ',array_filter([(string)($location['city']??''),(string)($location['region']??'')]))),is_array($item['locations']??null)?$item['locations']:[]))),
            'purchase_available'=>!empty($item['purchase_available']),
        ];
        $existing = $shortlisted[$projection['id']] ?? null;
        $cards[] = mg_task_agent_shortlist_product_card($projection,$filters['recipient_context'],is_array($existing),(string)($existing['id']??''));
    }
    mg_audit('multi_agent.discovery_completed','agent',['agent_id'=>$agentId,'result_count'=>count($cards),'used_ai'=>false],$userId);
    return [
        'reply'=>$cards ? 'I found '.count($cards).' currently published local gift options. Shortlist the best candidates or open a product to review it.' : 'No currently published products matched those filters. Try a broader category, location, or budget.',
        'cards'=>$cards,
        'system_intent'=>'local_gift_discovery',
        'filters'=>$filters,
    ];
}

function mg_task_agent_shortlist_cards(array $items): array
{
    return array_map(static fn(array $item): array=>mg_task_agent_shortlist_product_card(
        is_array($item['product']??null)?$item['product']:[],
        is_array($item['recipient_context']??null)?$item['recipient_context']:[],
        true,
        (string)($item['id']??'')
    ),array_slice($items,0,20));
}

function mg_task_agent_shortlist_for_model(array $items): array
{
    return array_map(static function(array $item): array {
        $product = is_array($item['product']??null)?$item['product']:[];
        return [
            'title'=>(string)($product['title']??''),
            'merchant'=>(string)($product['merchant']['name']??''),
            'value_cents'=>(int)($product['value_cents']??0),
            'currency'=>(string)($product['currency']??'USD'),
            'product_type'=>(string)($product['product_type']??''),
            'locations'=>array_slice($product['locations']??[],0,3),
            'reason'=>(string)($item['reason']??''),
        ];
    },array_slice($items,0,8));
}

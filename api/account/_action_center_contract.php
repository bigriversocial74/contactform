<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';
require_once __DIR__ . '/_action_center_wallet.php';
require_once __DIR__ . '/_action_center_product_media.php';

const MG_ACTION_CENTER_CONTRACT_VERSION = 2;

function mg_action_center_contract_text(mixed $value, int $limit = 5000): string
{
    $value = trim((string) $value);
    return $value === '' ? '' : mb_substr($value, 0, max(1, $limit));
}

function mg_action_center_contract_bool(mixed $value): bool
{
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (int) $value === 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function mg_action_center_contract_safe_url(mixed $value): string
{
    $url = trim((string) $value);
    if ($url === '' || mb_strlen($url) > 900 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $parts = parse_url($url);
    return is_array($parts)
        && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && trim((string) ($parts['host'] ?? '')) !== ''
        ? $url
        : '';
}

function mg_action_center_contract_metadata(array $item): array
{
    foreach (['_metadata', 'metadata_json', 'instance_metadata_json', 'metadata'] as $key) {
        $raw = $item[$key] ?? null;
        if (is_array($raw)) return $raw;
        if (!is_string($raw) || trim($raw) === '') continue;
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) return $decoded;
        } catch (Throwable) {
        }
    }
    return [];
}

function mg_action_center_contract_first_url(array $candidates): array
{
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $url = mg_action_center_contract_safe_url($candidate['url'] ?? '');
        if ($url === '') continue;
        return [
            'url' => $url,
            'source' => mg_action_center_contract_text($candidate['source'] ?? 'unknown', 80) ?: 'unknown',
        ];
    }
    return ['url' => '', 'source' => 'none'];
}

function mg_action_center_contract_nested_image(array $source, string $sourcePrefix): array
{
    $pack = is_array($source['media_pack'] ?? null) ? $source['media_pack'] : [];
    $candidates = [];
    foreach (['custom_gift_image_url', 'gift_image_url', 'reward_image_url', 'image_url', 'thumbnail_url', 'cover_image_url'] as $key) {
        $candidates[] = ['url' => $source[$key] ?? '', 'source' => $sourcePrefix . '.' . $key];
    }
    foreach (['custom_gift_image_url', 'gift_image_url', 'reward_image_url', 'image_url', 'thumbnail_url', 'cover_image_url'] as $key) {
        $candidates[] = ['url' => $pack[$key] ?? '', 'source' => $sourcePrefix . '.media_pack.' . $key];
    }
    return mg_action_center_contract_first_url($candidates);
}

function mg_action_center_contract_posts(array $item, array $metadata): array
{
    $posts = [];
    $sourcePosts = is_array($item['posts'] ?? null)
        ? $item['posts']
        : (is_array($metadata['posts'] ?? null) ? $metadata['posts'] : []);

    foreach (array_slice($sourcePosts, 0, 30) as $post) {
        if (!is_array($post)) continue;
        $type = strtolower(mg_action_center_contract_text($post['type'] ?? $post['media_type'] ?? 'content', 40));
        $url = mg_action_center_contract_safe_url($post['url'] ?? '');
        $posts[] = [
            'type' => $type !== '' ? $type : 'content',
            'title' => mg_action_center_contract_text($post['title'] ?? '', 240),
            'body' => mg_action_center_contract_text($post['body'] ?? '', 5000),
            'label' => mg_action_center_contract_text($post['meta'] ?? $post['label'] ?? '', 240),
            'url' => $url !== '' ? $url : null,
            'media_type' => mg_action_center_contract_text($post['media_type'] ?? $type, 40),
        ];
    }

    return $posts;
}

function mg_action_center_contract_business_names(PDO $pdo, array $items): array
{
    $merchantIds = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $merchantId = (int) ($item['merchant_user_id'] ?? 0);
        if ($merchantId < 1 && (string) ($item['source_system'] ?? '') === 'campaigns') {
            $merchantId = (int) ($item['sender_id'] ?? 0);
        }
        if ($merchantId > 0) $merchantIds[$merchantId] = true;
    }

    $businessNames = [];
    if ($merchantIds !== []) {
        try {
            $ids = array_keys($merchantIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT merchant_user_id,display_name,status,id FROM merchant_storefronts WHERE merchant_user_id IN ({$placeholders}) AND status IN ('published','draft') ORDER BY CASE status WHEN 'published' THEN 0 ELSE 1 END,id ASC");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $merchantId = (int) ($row['merchant_user_id'] ?? 0);
                $name = mg_action_center_contract_text($row['display_name'] ?? '', 190);
                if ($merchantId > 0 && $name !== '' && !isset($businessNames[$merchantId])) {
                    $businessNames[$merchantId] = $name;
                }
            }
        } catch (Throwable) {
            $businessNames = [];
        }
    }

    foreach ($items as &$item) {
        if (!is_array($item)) continue;
        $merchantId = (int) ($item['merchant_user_id'] ?? 0);
        if ($merchantId < 1 && (string) ($item['source_system'] ?? '') === 'campaigns') {
            $merchantId = (int) ($item['sender_id'] ?? 0);
        }
        $business = mg_action_center_contract_text(
            $businessNames[$merchantId] ?? $item['business_name'] ?? $item['merchant_name'] ?? '',
            190
        );
        $item['business_name'] = $business !== '' ? $business : 'Microgifter';
        $item['merchant_name'] = $item['business_name'];
    }
    unset($item);

    return $items;
}

function mg_action_center_contract_capabilities(array $item): array
{
    $folder = mg_action_center_folder((string) ($item['folder'] ?? 'inbox'));
    $state = strtolower(mg_action_center_contract_text($item['state'] ?? '', 80));
    $status = strtolower(mg_action_center_contract_text($item['instance_status'] ?? $state, 80));
    $isWallet = !empty($item['is_wallet_reward']);

    $canSend = $folder === 'inbox' && ($isWallet
        ? !in_array($state, ['expired', 'redeemed', 'cancelled'], true)
        : in_array($status, ['issued', 'delivered'], true));
    $canClaim = $folder === 'inbox' && ($isWallet
        ? in_array($state, ['claimable', 'received'], true)
        : in_array($status, ['issued', 'delivered', 'claim_pending'], true));
    $canFollowUp = mg_action_center_contract_bool($item['can_follow_up'] ?? false);
    $canMessage = array_key_exists('can_message', $item)
        ? mg_action_center_contract_bool($item['can_message'])
        : ($folder === 'claimed'
            && (string) ($item['sender_id'] ?? '') !== ''
            && (string) ($item['sender_id'] ?? '') !== (string) ($item['owner_user_id'] ?? ''));
    $canTip = mg_action_center_contract_bool($item['can_tip'] ?? false)
        && $folder === 'claimed'
        && $state === 'redeemed';
    $canRedeem = $folder === 'claimed' && in_array($state, ['redeemable', 'claimed'], true);

    $capabilities = [
        'open' => true,
        'load' => true,
        'send' => $canSend,
        'claim' => $canClaim,
        'redeem' => $canRedeem,
        'follow_up' => $canFollowUp,
        'message' => $canMessage,
        'tip' => $canTip,
        'mark_read' => true,
        'archive' => true,
    ];

    $reasons = [];
    if (!$canSend) $reasons['send'] = $folder !== 'inbox'
        ? 'Only currently owned Inbox gifts can be transferred.'
        : 'This gift is not in a transferable lifecycle state.';
    if (!$canClaim) $reasons['claim'] = $folder !== 'inbox'
        ? 'Claiming is available from the Inbox.'
        : 'This gift is not in a claimable lifecycle state.';
    if (!$canFollowUp) $reasons['follow_up'] = 'Follow Up is available only to the most recent sender while the recipient still owns the gift.';
    if (!$canMessage) $reasons['message'] = 'Messaging is not available for the current gift relationship.';
    if (!$canTip) $reasons['tip'] = 'Tipping is available after an eligible merchant redemption.';
    if (!$canRedeem) $reasons['redeem'] = 'This gift is not ready for merchant redemption.';

    return ['values' => $capabilities, 'reasons' => $reasons];
}

function mg_action_center_contract_item(array $item): array
{
    $metadata = mg_action_center_contract_metadata($item);
    $rewardMetadata = is_array($metadata['reward_template_metadata'] ?? null)
        ? $metadata['reward_template_metadata']
        : [];
    $posts = mg_action_center_contract_posts($item, $metadata);

    $productBasis = mg_action_center_contract_text($item['product_version_basis'] ?? '', 80);
    $productImage = mg_action_center_contract_safe_url($item['product_image_url'] ?? '');
    $customImage = mg_action_center_contract_nested_image($metadata, 'gift');
    $rewardImage = mg_action_center_contract_nested_image($rewardMetadata, 'reward');
    $postCover = ['url' => '', 'source' => 'none'];
    foreach ($posts as $post) {
        if (!in_array((string) ($post['type'] ?? ''), ['cover', 'image'], true)) continue;
        $url = mg_action_center_contract_safe_url($post['url'] ?? '');
        if ($url !== '') {
            $postCover = ['url' => $url, 'source' => 'media.post'];
            break;
        }
    }

    $exactProduct = $productBasis === 'exact_instance_version';
    $presentation = mg_action_center_contract_first_url([
        ['url' => $exactProduct ? $productImage : '', 'source' => 'catalog_product_version_cover'],
        ['url' => $metadata['custom_gift_image_url'] ?? $metadata['gift_image_url'] ?? '', 'source' => 'gift.custom_image'],
        ['url' => !$exactProduct ? $productImage : '', 'source' => 'catalog_product_current_cover'],
        $customImage,
        $rewardImage,
        $postCover,
        ['url' => $item['merchant_avatar_url'] ?? '', 'source' => 'merchant.logo'],
    ]);

    $productId = mg_action_center_contract_text($item['product_id'] ?? '', 80);
    $productVersionId = mg_action_center_contract_text($item['product_version_id'] ?? '', 80);
    $productTitle = mg_action_center_contract_text($item['product_title'] ?? '', 240);
    $productUrl = mg_action_center_contract_safe_url($item['product_url'] ?? '');
    $productStatus = mg_action_center_contract_text($item['product_status'] ?? '', 40);
    $productPublic = mg_action_center_contract_bool($item['product_is_public'] ?? ($productUrl !== ''));

    $linkedResource = null;
    if ($productId !== '') {
        $linkedResource = [
            'type' => 'catalog_product',
            'public_id' => $productId,
            'version_id' => $productVersionId !== '' ? $productVersionId : null,
            'product_type' => mg_action_center_contract_text($item['catalog_product_type'] ?? $item['product_type'] ?? '', 80),
            'title' => $productTitle !== '' ? $productTitle : null,
            'url' => $productPublic && $productUrl !== '' ? $productUrl : null,
            'is_public' => $productPublic,
            'status' => $productStatus !== '' ? $productStatus : ($productPublic ? 'published' : 'unavailable'),
            'availability' => $productPublic ? 'available' : 'unavailable',
            'version_basis' => $productBasis !== '' ? $productBasis : 'unknown',
        ];
    }

    $snapshotTitle = mg_action_center_contract_text(
        $item['title_snapshot'] ?? $item['template_name'] ?? $item['title'] ?? '',
        240
    );
    if ($snapshotTitle === '') $snapshotTitle = 'Microgift';
    $snapshotDescription = mg_action_center_contract_text(
        $item['description_snapshot'] ?? $item['message'] ?? '',
        5000
    );

    $source = [
        'system' => mg_action_center_contract_text($item['source_system'] ?? 'in_out_box', 80) ?: 'in_out_box',
        'type' => mg_action_center_contract_text($item['source_type'] ?? '', 80),
        'label' => mg_action_center_contract_text($item['source_label'] ?? '', 190),
        'detail' => mg_action_center_contract_text($item['source_detail'] ?? '', 500),
        'reference' => mg_action_center_contract_text($item['source_reference'] ?? '', 190),
    ];

    $capabilityContract = mg_action_center_contract_capabilities($item);

    return [
        'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION,
        'kind' => 'action_center_gift',
        'action_item_id' => mg_action_center_contract_text($item['action_item_id'] ?? '', 190),
        'folder' => mg_action_center_folder((string) ($item['folder'] ?? 'inbox')),
        'gift' => [
            'id' => mg_action_center_contract_text($item['instance_id'] ?? '', 190),
            'template_id' => mg_action_center_contract_text($item['template_id'] ?? '', 190) ?: null,
            'template_type' => mg_action_center_contract_text($item['gift_type'] ?? (!empty($item['is_wallet_reward']) ? 'reward' : ''), 80) ?: null,
            'status' => mg_action_center_contract_text($item['instance_status'] ?? $item['state'] ?? '', 80),
            'state' => mg_action_center_contract_text($item['state'] ?? '', 80),
            'snapshot' => [
                'title' => $snapshotTitle,
                'description' => $snapshotDescription,
                'value_cents' => max(0, (int) ($item['face_value_cents'] ?? 0)),
                'currency' => strtoupper(mg_action_center_contract_text($item['currency'] ?? 'USD', 3)) ?: 'USD',
                'expires_at' => $item['expires_at'] ?? null,
            ],
        ],
        'presentation' => [
            'title_source' => 'gift_snapshot',
            'image_url' => $presentation['url'] !== '' ? $presentation['url'] : null,
            'image_source' => $presentation['source'],
        ],
        'linked_resource' => $linkedResource,
        'source' => $source,
        'participants' => [
            'sender' => ['name' => mg_action_center_contract_text($item['sender_name'] ?? '', 190) ?: null],
            'recipient' => ['name' => mg_action_center_contract_text($item['recipient_name'] ?? '', 190) ?: null],
        ],
        'merchant' => [
            'name' => mg_action_center_contract_text($item['business_name'] ?? $item['merchant_name'] ?? '', 190) ?: 'Microgifter',
            'avatar_url' => ($avatar = mg_action_center_contract_safe_url($item['merchant_avatar_url'] ?? '')) !== '' ? $avatar : null,
        ],
        'location' => [
            'public_id' => mg_action_center_contract_text($item['location_id'] ?? '', 190) ?: null,
            'name' => mg_action_center_contract_text($item['location_name'] ?? '', 190) ?: null,
        ],
        'redemption' => [
            'public_id' => mg_action_center_contract_text($item['redemption_id'] ?? '', 190) ?: null,
            'status' => mg_action_center_contract_text($item['redemption_status'] ?? '', 80) ?: null,
            'redeemed_at' => $item['merchant_redeemed_at'] ?? $item['redeemed_at'] ?? null,
        ],
        'activity' => [
            'received_at' => $item['received_at'] ?? $item['first_received_at'] ?? null,
            'sent_at' => $item['sent_at'] ?? null,
            'claimed_at' => $item['claimed_at'] ?? null,
            'redeemed_at' => $item['redeemed_at'] ?? null,
            'updated_at' => $item['updated_at'] ?? null,
            'last_delivery_at' => $item['last_delivery_event_at'] ?? null,
            'resend_count' => max(0, (int) ($item['resend_count'] ?? 0)),
            'last_follow_up_at' => $item['last_follow_up_at'] ?? null,
            'follow_up_count' => max(0, (int) ($item['follow_up_count'] ?? 0)),
            'read_at' => $item['read_at'] ?? null,
        ],
        'capabilities' => $capabilityContract['values'],
        'capability_reasons' => $capabilityContract['reasons'],
        'media' => [
            'posts' => $posts,
            'count' => count($posts) + ($presentation['url'] !== '' ? 1 : 0),
            'has_media' => $posts !== [] || $presentation['url'] !== '',
        ],
        'flags' => [
            'wallet_fallback' => !empty($item['is_wallet_reward']),
            'demo_preview' => !empty($item['is_demo_preview']) || !empty($item['is_demo']),
            'system_demo' => !empty($item['is_system_demo']),
        ],
    ];
}

function mg_action_center_contract_items(PDO $pdo, int $userId, array $items): array
{
    $items = mg_action_center_contract_business_names($pdo, $items);
    $items = mg_action_center_attach_product_media($pdo, $userId, $items);
    return array_values(array_map('mg_action_center_contract_item', $items));
}

function mg_action_center_contract_view(array $contract): array
{
    $gift = is_array($contract['gift'] ?? null) ? $contract['gift'] : [];
    $snapshot = is_array($gift['snapshot'] ?? null) ? $gift['snapshot'] : [];
    $presentation = is_array($contract['presentation'] ?? null) ? $contract['presentation'] : [];
    $linked = is_array($contract['linked_resource'] ?? null) ? $contract['linked_resource'] : [];
    $source = is_array($contract['source'] ?? null) ? $contract['source'] : [];
    $participants = is_array($contract['participants'] ?? null) ? $contract['participants'] : [];
    $sender = is_array($participants['sender'] ?? null) ? $participants['sender'] : [];
    $recipient = is_array($participants['recipient'] ?? null) ? $participants['recipient'] : [];
    $merchant = is_array($contract['merchant'] ?? null) ? $contract['merchant'] : [];
    $location = is_array($contract['location'] ?? null) ? $contract['location'] : [];
    $redemption = is_array($contract['redemption'] ?? null) ? $contract['redemption'] : [];
    $activity = is_array($contract['activity'] ?? null) ? $contract['activity'] : [];
    $capabilities = is_array($contract['capabilities'] ?? null) ? $contract['capabilities'] : [];
    $media = is_array($contract['media'] ?? null) ? $contract['media'] : [];
    $flags = is_array($contract['flags'] ?? null) ? $contract['flags'] : [];

    return [
        'action_item_id' => (string) ($contract['action_item_id'] ?? ''),
        'folder' => (string) ($contract['folder'] ?? 'inbox'),
        'state' => (string) ($gift['state'] ?? ''),
        'instance_id' => (string) ($gift['id'] ?? ''),
        'instance_status' => (string) ($gift['status'] ?? ''),
        'template_id' => (string) ($gift['template_id'] ?? ''),
        'template_name' => (string) ($snapshot['title'] ?? 'Microgift'),
        'message' => (string) ($snapshot['description'] ?? ''),
        'face_value_cents' => (int) ($snapshot['value_cents'] ?? 0),
        'currency' => (string) ($snapshot['currency'] ?? 'USD'),
        'expires_at' => $snapshot['expires_at'] ?? null,
        'product_type' => (string) ($linked['product_type'] ?? $gift['template_type'] ?? 'gift'),
        'product_id' => (string) ($linked['public_id'] ?? ''),
        'product_version_id' => (string) ($linked['version_id'] ?? ''),
        'product_url' => (string) ($linked['url'] ?? ''),
        'product_image_url' => (string) ($presentation['image_url'] ?? ''),
        'image_source' => (string) ($presentation['image_source'] ?? 'none'),
        'sender_name' => (string) ($sender['name'] ?? ''),
        'recipient_name' => (string) ($recipient['name'] ?? ''),
        'merchant_name' => (string) ($merchant['name'] ?? 'Microgifter'),
        'business_name' => (string) ($merchant['name'] ?? 'Microgifter'),
        'merchant_avatar_url' => (string) ($merchant['avatar_url'] ?? ''),
        'location_id' => (string) ($location['public_id'] ?? ''),
        'location_name' => (string) ($location['name'] ?? ''),
        'redemption_id' => (string) ($redemption['public_id'] ?? ''),
        'redemption_status' => (string) ($redemption['status'] ?? ''),
        'merchant_redeemed_at' => $redemption['redeemed_at'] ?? null,
        'received_at' => $activity['received_at'] ?? null,
        'first_received_at' => $activity['received_at'] ?? null,
        'sent_at' => $activity['sent_at'] ?? null,
        'claimed_at' => $activity['claimed_at'] ?? null,
        'redeemed_at' => $activity['redeemed_at'] ?? null,
        'updated_at' => $activity['updated_at'] ?? null,
        'last_delivery_event_at' => $activity['last_delivery_at'] ?? null,
        'resend_count' => (int) ($activity['resend_count'] ?? 0),
        'last_follow_up_at' => $activity['last_follow_up_at'] ?? null,
        'follow_up_count' => (int) ($activity['follow_up_count'] ?? 0),
        'read_at' => $activity['read_at'] ?? null,
        'source_system' => (string) ($source['system'] ?? ''),
        'source_type' => (string) ($source['type'] ?? ''),
        'source_label' => (string) ($source['label'] ?? ''),
        'source_detail' => (string) ($source['detail'] ?? ''),
        'source_reference' => (string) ($source['reference'] ?? ''),
        'can_send' => !empty($capabilities['send']),
        'can_claim' => !empty($capabilities['claim']),
        'can_redeem' => !empty($capabilities['redeem']),
        'can_follow_up' => !empty($capabilities['follow_up']),
        'can_message' => !empty($capabilities['message']),
        'can_tip' => !empty($capabilities['tip']),
        'can_load' => !empty($capabilities['load']),
        'posts' => is_array($media['posts'] ?? null) ? $media['posts'] : [],
        'is_wallet_reward' => !empty($flags['wallet_fallback']),
        'is_demo' => !empty($flags['demo_preview']),
        'is_system_demo' => !empty($flags['system_demo']),
        'contract' => $contract,
    ];
}

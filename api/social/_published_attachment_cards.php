<?php
declare(strict_types=1);

function mg_feed_attachment_placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}

function mg_feed_attachment_text(mixed $value, int $limit = 220): ?string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    return $text === '' ? null : mb_substr($text, 0, $limit);
}

function mg_feed_attachment_asset_url(?string $provider, ?string $storageKey): ?string
{
    $provider = strtolower(trim((string)$provider));
    $storageKey = trim((string)$storageKey);
    if ($storageKey === '' || str_contains($storageKey, '..') || str_contains($storageKey, "\\")) return null;
    if ($provider === 'local') return '/' . ltrim($storageKey, '/');
    if (preg_match('#^https://#i', $storageKey) === 1 && filter_var($storageKey, FILTER_VALIDATE_URL)) return $storageKey;
    return null;
}

function mg_feed_attachment_rows_by_id(PDO $pdo, string $sql, array $ids): array
{
    if ($ids === []) return [];
    $stmt = $pdo->prepare(str_replace(':ids', mg_feed_attachment_placeholders($ids), $sql));
    $stmt->execute(array_values($ids));
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int)$row['id']] = $row;
    return $rows;
}

function mg_feed_attachment_post_image_urls(PDO $pdo, array $posts): array
{
    $versionIds = [];
    foreach ($posts as $post) {
        $versionId = (int)($post['current_version_id'] ?? 0);
        if ($versionId > 0) $versionIds[$versionId] = $versionId;
    }
    if ($versionIds === []) return [];

    $stmt = $pdo->prepare(
        "SELECT fpe.feed_post_version_id version_id,a.storage_provider,a.storage_key
         FROM feed_post_elements fpe
         INNER JOIN catalog_assets a ON a.id=fpe.asset_id AND a.status='ready' AND a.asset_type='image'
         WHERE fpe.feed_post_version_id IN (" . mg_feed_attachment_placeholders(array_values($versionIds)) . ")
           AND fpe.element_type='image'
         ORDER BY fpe.feed_post_version_id,fpe.sort_order,fpe.id"
    );
    $stmt->execute(array_values($versionIds));
    $images = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $versionId = (int)$row['version_id'];
        if (isset($images[$versionId])) continue;
        $url = mg_feed_attachment_asset_url($row['storage_provider'] ?? null, $row['storage_key'] ?? null);
        if ($url !== null) $images[$versionId] = $url;
    }
    return $images;
}

function mg_feed_published_attachment_cards(PDO $pdo, array $posts, ?int $viewerId): array
{
    $productIds = [];
    $microgiftIds = [];
    $planIds = [];
    foreach ($posts as $post) {
        if (!empty($post['catalog_product_id'])) $productIds[(int)$post['catalog_product_id']] = (int)$post['catalog_product_id'];
        if (!empty($post['linked_microgift_instance_id'])) $microgiftIds[(int)$post['linked_microgift_instance_id']] = (int)$post['linked_microgift_instance_id'];
        if (!empty($post['subscription_plan_id'])) $planIds[(int)$post['subscription_plan_id']] = (int)$post['subscription_plan_id'];
    }

    $products = mg_feed_attachment_rows_by_id($pdo,
        "SELECT p.id,p.public_id,p.slug,p.status,v.title,v.description,v.unit_value_cents,v.currency,
                a.storage_provider preview_provider,a.storage_key preview_key
         FROM catalog_products p
         LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
         LEFT JOIN catalog_product_version_assets pva ON pva.id=(
             SELECT pva2.id FROM catalog_product_version_assets pva2
             INNER JOIN catalog_assets a2 ON a2.id=pva2.asset_id
             WHERE pva2.product_version_id=p.current_version_id
               AND a2.status='ready' AND a2.asset_type='image'
             ORDER BY FIELD(pva2.role,'cover','thumbnail','gallery','inside_cover','carousel','other'),pva2.sort_order,pva2.id
             LIMIT 1
         )
         LEFT JOIN catalog_assets a ON a.id=pva.asset_id
         WHERE p.id IN (:ids) AND p.status='published'",
        array_values($productIds)
    );

    $postImageUrls = mg_feed_attachment_post_image_urls($pdo, $posts);

    $microgifts = mg_feed_attachment_rows_by_id($pdo,
        "SELECT i.id,i.public_id,i.title_snapshot title,i.description_snapshot description,i.status,
                i.face_value_cents,i.currency,i.source_type,i.owner_user_id,i.issuer_user_id,
                i.recipient_user_id,i.issued_at,i.expires_at,
                a.storage_provider preview_provider,a.storage_key preview_key
         FROM microgift_instances i
         LEFT JOIN catalog_product_version_assets pva ON pva.id=(
             SELECT pva2.id FROM catalog_product_version_assets pva2
             INNER JOIN catalog_assets a2 ON a2.id=pva2.asset_id
             WHERE pva2.product_version_id=i.product_version_id
               AND a2.status='ready' AND a2.asset_type='image'
             ORDER BY FIELD(pva2.role,'cover','thumbnail','gallery','inside_cover','carousel','other'),pva2.sort_order,pva2.id
             LIMIT 1
         )
         LEFT JOIN catalog_assets a ON a.id=pva.asset_id
         WHERE i.id IN (:ids)",
        array_values($microgiftIds)
    );

    $plans = mg_feed_attachment_rows_by_id($pdo,
        "SELECT sp.id,sp.public_id,sp.owner_user_id,sp.name title,sp.description,sp.amount_cents,
                sp.currency,sp.interval_unit,sp.interval_count,sp.trial_days,sp.status
         FROM subscription_plans sp WHERE sp.id IN (:ids) AND sp.status='active'",
        array_values($planIds)
    );

    $activePlans = [];
    if ($viewerId !== null && $planIds !== []) {
        $ids = array_values($planIds);
        $stmt = $pdo->prepare(
            "SELECT DISTINCT plan_id FROM subscriptions
             WHERE subscriber_user_id=? AND plan_id IN (" . mg_feed_attachment_placeholders($ids) . ")
               AND recovery_status='clear'
               AND status IN ('trialing','active','cancel_pending')
               AND current_period_end>NOW()"
        );
        $stmt->execute(array_merge([$viewerId], $ids));
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $planId) $activePlans[(int)$planId] = true;
    }

    $result = [];
    foreach ($posts as $post) {
        $postId = (string)$post['public_id'];
        $profileSlug = (string)($post['profile_slug'] ?? '');
        $cards = [];

        $productId = (int)($post['catalog_product_id'] ?? 0);
        if ($productId > 0 && isset($products[$productId])) {
            $product = $products[$productId];
            $productImageUrl = mg_feed_attachment_asset_url($product['preview_provider'] ?? null, $product['preview_key'] ?? null);
            if ($productImageUrl === null) {
                $productImageUrl = $postImageUrls[(int)($post['current_version_id'] ?? 0)] ?? null;
            }
            $cards[] = [
                'kind'=>'product','eyebrow'=>'Product','title'=>(string)($product['title'] ?: 'Microgifter product'),
                'description'=>mg_feed_attachment_text($product['description'] ?? null),
                'value_cents'=>(int)($product['unit_value_cents'] ?? 0),'currency'=>(string)($product['currency'] ?? 'USD'),
                'status'=>(string)$product['status'],'image_url'=>$productImageUrl,
                'access'=>['state'=>'public','label'=>'Available to everyone'],
                'action'=>['state'=>'enabled','label'=>'View product','url'=>'/product.php?p='.rawurlencode((string)$product['slug'])],
            ];
        }

        $microgiftId = (int)($post['linked_microgift_instance_id'] ?? 0);
        if ($microgiftId > 0 && isset($microgifts[$microgiftId])) {
            $gift = $microgifts[$microgiftId];
            $canOpen = $viewerId !== null && in_array($viewerId, [
                (int)($gift['owner_user_id'] ?? 0),(int)($gift['issuer_user_id'] ?? 0),(int)($gift['recipient_user_id'] ?? 0),
            ], true);
            $giftStatus = (string)($gift['status'] ?? 'issued');
            if (!empty($gift['expires_at']) && strtotime((string)$gift['expires_at']) < time() && !in_array($giftStatus,['redeemed','claimed','cancelled'],true)) $giftStatus='expired';
            if ($canOpen) {
                $access=['state'=>'available','label'=>'Available to you'];
                $action=['state'=>'enabled','label'=>'Open Microgift','url'=>'/inbox.php?item='.rawurlencode((string)$gift['public_id'])];
            } elseif ($viewerId === null) {
                $access=['state'=>'signin','label'=>'Sign in to check access'];
                $action=['state'=>'signin','label'=>'Sign in','url'=>'/signin.php?return='.rawurlencode('/feed.php')];
            } else {
                $access=['state'=>'restricted','label'=>'Private to its participants'];
                $action=['state'=>'restricted','label'=>'Private Microgift','url'=>null];
            }
            $cards[] = [
                'kind'=>'microgift','eyebrow'=>'Microgift','title'=>(string)($gift['title'] ?: 'Attached Microgift'),
                'description'=>mg_feed_attachment_text($gift['description'] ?? null),
                'value_cents'=>(int)($gift['face_value_cents'] ?? 0),'currency'=>(string)($gift['currency'] ?? 'USD'),
                'status'=>$giftStatus,'image_url'=>mg_feed_attachment_asset_url($gift['preview_provider'] ?? null,$gift['preview_key'] ?? null),
                'access'=>$access,'action'=>$action,
            ];
        }

        $planId = (int)($post['subscription_plan_id'] ?? 0);
        if ($planId > 0 && isset($plans[$planId])) {
            $plan = $plans[$planId];
            $isOwner = $viewerId !== null && $viewerId === (int)$plan['owner_user_id'];
            $isMember = isset($activePlans[$planId]);
            $profileUrl = $profileSlug !== '' ? '/profile.php?slug='.rawurlencode($profileSlug).'&plan='.rawurlencode((string)$plan['public_id']) : '/feed.php';
            if ($isOwner) $access=['state'=>'owner','label'=>'Your membership plan'];
            elseif ($isMember) $access=['state'=>'member','label'=>'Active membership'];
            elseif ($viewerId === null) $access=['state'=>'signin','label'=>'Sign in to view membership'];
            else $access=['state'=>'available','label'=>'Membership available'];
            $actionUrl = $viewerId === null ? '/signin.php?return='.rawurlencode($profileUrl) : $profileUrl;
            $cards[] = [
                'kind'=>'plan','eyebrow'=>'Member access','title'=>(string)($plan['title'] ?: 'Membership plan'),
                'description'=>mg_feed_attachment_text($plan['description'] ?? null),
                'value_cents'=>(int)($plan['amount_cents'] ?? 0),'currency'=>(string)($plan['currency'] ?? 'USD'),
                'status'=>(string)$plan['status'],'image_url'=>null,
                'interval_unit'=>(string)($plan['interval_unit'] ?? 'month'),'interval_count'=>max(1,(int)($plan['interval_count'] ?? 1)),
                'trial_days'=>max(0,(int)($plan['trial_days'] ?? 0)),'access'=>$access,
                'action'=>['state'=>$viewerId===null?'signin':'enabled','label'=>$isMember?'View membership':($isOwner?'View member page':'Explore membership'),'url'=>$actionUrl],
            ];
        }

        $result[$postId] = $cards;
    }
    return $result;
}

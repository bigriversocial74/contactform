<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/social/_social.php';
require_once dirname(__DIR__) . '/tips/_public_availability.php';
require_once dirname(__DIR__) . '/merchant/_storefront.php';

const MG_PUBLIC_PROFILE_DEFAULT_LIMIT = 12;
const MG_PUBLIC_PROFILE_MAX_LIMIT = 24;

function mg_public_profile_query_count_reset(): void
{
    $GLOBALS['mg_public_profile_query_count'] = 0;
}

function mg_public_profile_query_count_tick(int $amount = 1): void
{
    $GLOBALS['mg_public_profile_query_count'] = (int)($GLOBALS['mg_public_profile_query_count'] ?? 0) + max(0, $amount);
}

function mg_public_profile_query_count(): int
{
    return (int)($GLOBALS['mg_public_profile_query_count'] ?? 0);
}

function mg_public_profile_query(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    mg_public_profile_query_count_tick();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function mg_public_profile_slug(string $slug): string
{
    $slug = strtolower(trim($slug));
    if ($slug === '' || strlen($slug) > 120 || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,118}[a-z0-9])?$/', $slug) !== 1) {
        throw new InvalidArgumentException('Profile not found.');
    }
    return $slug;
}

function mg_public_profile_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => MG_PUBLIC_PROFILE_DEFAULT_LIMIT]]);
    return max(1, min((int)$limit, MG_PUBLIC_PROFILE_MAX_LIMIT));
}

function mg_public_profile_cursor_encode(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

function mg_public_profile_cursor_decode(?string $cursor): ?array
{
    $cursor = trim((string)$cursor);
    if ($cursor === '') return null;
    if (strlen($cursor) > 700 || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
        throw new InvalidArgumentException('Invalid pagination cursor.');
    }
    $padding = (4 - (strlen($cursor) % 4)) % 4;
    $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
    if (!is_string($decoded) || strlen($decoded) > 512) throw new InvalidArgumentException('Invalid pagination cursor.');
    $payload = json_decode($decoded, true);
    if (!is_array($payload)) throw new InvalidArgumentException('Invalid pagination cursor.');
    return $payload;
}

function mg_public_profile_safe_url(mixed $value, bool $allowRelative = false): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 600 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;

    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        $parts = parse_url($url);
        if ($parts !== false && !isset($parts['scheme']) && !isset($parts['host']) && !isset($parts['user']) && !isset($parts['pass'])) return $url;
        return null;
    }

    if (filter_var($url, FILTER_VALIDATE_URL) === false) return null;
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) return null;
    if (!in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true)) return null;
    if (isset($parts['user']) || isset($parts['pass'])) return null;
    return $url;
}

function mg_public_profile_media_url(?string $assetPublicId): ?string
{
    $assetPublicId = strtolower(trim((string)$assetPublicId));
    if ($assetPublicId === '' || preg_match('/^[a-f0-9-]{36}$/', $assetPublicId) !== 1) return null;
    return '/api/public/media.php?asset=' . rawurlencode($assetPublicId);
}

function mg_public_profile_session_viewer(PDO $pdo): ?array
{
    $sessionUser = mg_current_user();
    $userId = (int)($sessionUser['id'] ?? 0);
    if ($userId < 1 || session_id() === '') return null;

    $stmt = $pdo->prepare(
        "SELECT u.id,u.status
         FROM users u
         INNER JOIN user_sessions s ON s.user_id=u.id
         WHERE u.id=? AND s.session_hash=? AND s.revoked_at IS NULL
           AND (s.expires_at IS NULL OR s.expires_at>NOW())
         LIMIT 1"
    );
    $stmt->execute([$userId, mg_current_session_hash()]);
    $viewer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$viewer || (string)$viewer['status'] !== 'active') return null;
    return ['id' => (int)$viewer['id'], 'status' => 'active'];
}

function mg_public_profile_load(PDO $pdo, string $slug): array
{
    $stmt = mg_public_profile_query(
        $pdo,
        'SELECT pp.*,u.status AS user_status
         FROM public_profiles pp
         INNER JOIN users u ON u.id=pp.user_id
         WHERE pp.slug=? LIMIT 1',
        [$slug]
    );
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) throw new RuntimeException('Profile not found.');
    return $profile;
}

function mg_public_profile_viewer_is_active(PDO $pdo, ?int $viewerId): ?int
{
    if ($viewerId === null || $viewerId < 1) return null;
    $stmt = mg_public_profile_query($pdo, "SELECT id FROM users WHERE id=? AND status='active' LIMIT 1", [$viewerId]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    return $id > 0 ? $id : null;
}

function mg_public_profile_assert_access(PDO $pdo, array $profile, ?int $viewerId, bool $preview): array
{
    $ownerId = (int)$profile['user_id'];
    $isOwner = $viewerId !== null && $viewerId === $ownerId;

    if ((string)$profile['user_status'] !== 'active') throw new RuntimeException('Profile not found.');

    if ($preview) {
        if (!$isOwner || in_array((string)$profile['status'], ['hidden', 'suspended'], true)) {
            throw new RuntimeException('Profile not found.');
        }
    } elseif ((string)$profile['status'] !== 'active' || !in_array((string)$profile['visibility'], ['public', 'unlisted'], true)) {
        throw new RuntimeException('Profile not found.');
    }

    if (!$isOwner && $viewerId !== null) {
        mg_public_profile_query_count_tick();
        if (mg_social_is_blocked($pdo, $viewerId, $ownerId)) throw new RuntimeException('Profile not found.');
    }

    return ['is_owner' => $isOwner, 'preview' => $preview && $isOwner];
}

function mg_public_profile_identity(array $profile, array $access): array
{
    return [
        'id' => (string)$profile['public_id'],
        'slug' => (string)$profile['slug'],
        'display_name' => (string)$profile['display_name'],
        'headline' => $profile['headline'] !== null ? (string)$profile['headline'] : null,
        'biography' => $profile['bio'] !== null ? (string)$profile['bio'] : null,
        'avatar_url' => mg_public_profile_safe_url($profile['avatar_url'] ?? null, true),
        'cover_url' => mg_public_profile_safe_url($profile['cover_url'] ?? null, true),
        'location_label' => $profile['location_label'] !== null ? (string)$profile['location_label'] : null,
        'website_url' => mg_public_profile_safe_url($profile['website_url'] ?? null),
        'profile_type' => (string)$profile['profile_type'],
        'visibility' => (string)$profile['visibility'],
        'published_at' => $profile['published_at'] !== null ? (string)$profile['published_at'] : null,
        'availability' => [
            'is_owner' => (bool)$access['is_owner'],
            'is_preview' => (bool)$access['preview'],
        ],
    ];
}

function mg_public_profile_links(PDO $pdo, int $profileId): array
{
    $stmt = mg_public_profile_query(
        $pdo,
        'SELECT public_id,label,url,link_type,sort_order
         FROM public_profile_links
         WHERE profile_id=? AND is_active=1
         ORDER BY sort_order ASC,public_id ASC',
        [$profileId]
    );
    $links = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $url = mg_public_profile_safe_url($row['url'] ?? null);
        if ($url === null) continue;
        $links[] = [
            'id' => (string)$row['public_id'],
            'label' => (string)$row['label'],
            'url' => $url,
            'type' => (string)$row['link_type'],
            'sort_order' => (int)$row['sort_order'],
        ];
    }
    return $links;
}

function mg_public_profile_sections(PDO $pdo, int $profileId): array
{
    $stmt = mg_public_profile_query(
        $pdo,
        'SELECT public_id,section_type,title,body,sort_order
         FROM public_profile_sections
         WHERE profile_id=? AND is_active=1
         ORDER BY sort_order ASC,public_id ASC',
        [$profileId]
    );
    return array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'type' => (string)$row['section_type'],
        'title' => $row['title'] !== null ? (string)$row['title'] : null,
        'body' => $row['body'] !== null ? (string)$row['body'] : null,
        'sort_order' => (int)$row['sort_order'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_public_profile_storefront(PDO $pdo, int $ownerId): ?array
{
    mg_public_profile_query_count_tick();
    $store = mg_storefront_owned($pdo, $ownerId);
    if (!$store || (string)$store['status'] !== 'published') return null;

    mg_public_profile_query_count_tick();
    $revision = mg_storefront_revision($pdo, (int)$store['id'], 'published');
    if ($revision && (string)$revision['revision_status'] !== 'published') $revision = null;

    $logoId = $revision ? $revision['logo_asset_id'] : $store['logo_asset_id'];
    $coverId = $revision ? $revision['cover_asset_id'] : $store['cover_asset_id'];
    $assetIds = array_values(array_unique(array_filter([(int)$logoId, (int)$coverId])));
    $assets = [];
    if ($assetIds !== []) {
        $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
        $stmt = mg_public_profile_query($pdo, "SELECT id,public_id FROM catalog_assets WHERE id IN ({$placeholders}) AND status='ready'", $assetIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $asset) $assets[(int)$asset['id']] = (string)$asset['public_id'];
    }

    return [
        '_id' => (int)$store['id'],
        '_revision_id' => $revision ? (int)$revision['id'] : null,
        'id' => (string)$store['public_id'],
        'slug' => (string)$store['slug'],
        'display_name' => (string)($revision ? $revision['display_name'] : $store['display_name']),
        'headline' => ($revision ? $revision['headline'] : $store['headline']) !== null ? (string)($revision ? $revision['headline'] : $store['headline']) : null,
        'description' => ($revision ? $revision['description'] : $store['description']) !== null ? (string)($revision ? $revision['description'] : $store['description']) : null,
        'logo_url' => isset($assets[(int)$logoId]) ? mg_public_profile_media_url($assets[(int)$logoId]) : null,
        'cover_url' => isset($assets[(int)$coverId]) ? mg_public_profile_media_url($assets[(int)$coverId]) : null,
        'url' => '/store.php?s=' . rawurlencode((string)$store['slug']),
    ];
}

function mg_public_profile_products(PDO $pdo, int $ownerId, ?array $storefront, ?string $cursor, int $limit): array
{
    if ($storefront === null) return ['items' => [], 'next_cursor' => null, 'has_more' => false, 'limit' => $limit];

    $decoded = mg_public_profile_cursor_decode($cursor);
    $whereCursor = '';
    $revisionId = $storefront['_revision_id'];

    if ($revisionId !== null) {
        $params = [$ownerId, (int)$revisionId];
        if ($decoded !== null) {
            if (($decoded['kind'] ?? null) !== 'revision' || !isset($decoded['featured'], $decoded['sort'], $decoded['id'])) {
                throw new InvalidArgumentException('Invalid pagination cursor.');
            }
            $featured = (int)$decoded['featured'];
            $sort = (int)$decoded['sort'];
            $id = (string)$decoded['id'];
            if (!in_array($featured, [0, 1], true) || $sort < 0 || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
                throw new InvalidArgumentException('Invalid pagination cursor.');
            }
            $whereCursor = ' AND (rp.is_featured<? OR (rp.is_featured=? AND rp.sort_order>?) OR (rp.is_featured=? AND rp.sort_order=? AND cp.public_id>?))';
            array_push($params, $featured, $featured, $sort, $featured, $sort, $id);
        }
        $sql = "SELECT cp.public_id,cp.slug,cp.product_type,cpv.public_id AS version_id,cpv.title,cpv.description,
                       cpv.unit_value_cents,cpv.currency,rp.is_featured,rp.sort_order,cover.public_id AS cover_asset_id
                FROM merchant_storefront_revision_products rp
                INNER JOIN catalog_products cp ON cp.id=rp.catalog_product_id AND cp.merchant_user_id=? AND cp.status='published'
                INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published'
                LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id AND pva.role='cover'
                  AND pva.id=(SELECT MIN(pva2.id) FROM catalog_product_version_assets pva2 WHERE pva2.product_version_id=cpv.id AND pva2.role='cover')
                LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id AND cover.status='ready'
                WHERE rp.storefront_revision_id=? AND rp.visibility='visible'{$whereCursor}
                ORDER BY rp.is_featured DESC,rp.sort_order ASC,cp.public_id ASC
                LIMIT " . ($limit + 1);
    } else {
        $params = [$ownerId];
        if ($decoded !== null) {
            if (($decoded['kind'] ?? null) !== 'owner' || !isset($decoded['time'], $decoded['id'])) {
                throw new InvalidArgumentException('Invalid pagination cursor.');
            }
            $time = (string)$decoded['time'];
            $id = (string)$decoded['id'];
            if (strtotime($time) === false || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
                throw new InvalidArgumentException('Invalid pagination cursor.');
            }
            $whereCursor = ' AND (COALESCE(cp.published_at,cp.created_at)<? OR (COALESCE(cp.published_at,cp.created_at)=? AND cp.public_id<?))';
            array_push($params, $time, $time, $id);
        }
        $sql = "SELECT cp.public_id,cp.slug,cp.product_type,cpv.public_id AS version_id,cpv.title,cpv.description,
                       cpv.unit_value_cents,cpv.currency,0 AS is_featured,0 AS sort_order,
                       COALESCE(cp.published_at,cp.created_at) AS sort_time,cover.public_id AS cover_asset_id
                FROM catalog_products cp
                INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published'
                LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id AND pva.role='cover'
                  AND pva.id=(SELECT MIN(pva2.id) FROM catalog_product_version_assets pva2 WHERE pva2.product_version_id=cpv.id AND pva2.role='cover')
                LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id AND cover.status='ready'
                WHERE cp.merchant_user_id=? AND cp.status='published'{$whereCursor}
                ORDER BY COALESCE(cp.published_at,cp.created_at) DESC,cp.public_id DESC
                LIMIT " . ($limit + 1);
    }

    $rows = mg_public_profile_query($pdo, $sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);

    $items = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'version_id' => (string)$row['version_id'],
        'slug' => (string)$row['slug'],
        'type' => (string)$row['product_type'],
        'title' => (string)$row['title'],
        'description' => $row['description'] !== null ? (string)$row['description'] : null,
        'amount_cents' => (int)$row['unit_value_cents'],
        'currency' => (string)$row['currency'],
        'featured' => (bool)$row['is_featured'],
        'sort_order' => (int)$row['sort_order'],
        'cover_url' => mg_public_profile_media_url($row['cover_asset_id'] ?? null),
        'url' => '/product.php?p=' . rawurlencode((string)$row['slug']),
    ], $rows);

    $next = null;
    if ($hasMore && $rows !== []) {
        $last = $rows[array_key_last($rows)];
        $next = $revisionId !== null
            ? mg_public_profile_cursor_encode(['kind' => 'revision', 'featured' => (int)$last['is_featured'], 'sort' => (int)$last['sort_order'], 'id' => (string)$last['public_id']])
            : mg_public_profile_cursor_encode(['kind' => 'owner', 'time' => (string)$last['sort_time'], 'id' => (string)$last['public_id']]);
    }

    return ['items' => $items, 'next_cursor' => $next, 'has_more' => $hasMore, 'limit' => $limit];
}

function mg_public_profile_post_media(mixed $raw): array
{
    if (is_string($raw)) $raw = json_decode($raw, true);
    if (!is_array($raw)) return [];
    $items = array_is_list($raw) ? $raw : [$raw];
    $safe = [];
    foreach ($items as $item) {
        if (count($safe) >= 12) break;
        if (is_string($item)) $item = ['url' => $item];
        if (!is_array($item)) continue;
        $url = mg_public_profile_safe_url($item['url'] ?? null, true);
        if ($url === null) continue;
        $safe[] = [
            'url' => $url,
            'type' => isset($item['type']) ? mb_substr((string)$item['type'], 0, 40) : null,
            'alt' => isset($item['alt']) ? mb_substr((string)$item['alt'], 0, 240) : null,
            'caption' => isset($item['caption']) ? mb_substr((string)$item['caption'], 0, 500) : null,
        ];
    }
    return $safe;
}

function mg_public_profile_posts(PDO $pdo, int $ownerId, ?int $viewerId, array $socialContext, ?string $cursor, int $limit): array
{
    $decoded = mg_public_profile_cursor_decode($cursor);
    $params = [$ownerId];
    $whereCursor = '';
    if ($decoded !== null) {
        if (($decoded['kind'] ?? null) !== 'post' || !isset($decoded['time'], $decoded['id'])) {
            throw new InvalidArgumentException('Invalid pagination cursor.');
        }
        $time = (string)$decoded['time'];
        $id = (string)$decoded['id'];
        if (strtotime($time) === false || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
            throw new InvalidArgumentException('Invalid pagination cursor.');
        }
        $whereCursor = ' AND (fp.created_at<? OR (fp.created_at=? AND fp.public_id<?))';
        array_push($params, $time, $time, $id);
    }

    $scanLimit = min(200, max(25, $limit * 5));
    $stmt = mg_public_profile_query(
        $pdo,
        "SELECT fp.*,cp.public_id AS product_public_id,sp.public_id AS plan_public_id
         FROM feed_posts fp
         LEFT JOIN catalog_products cp ON cp.id=fp.catalog_product_id AND cp.status='published'
         LEFT JOIN subscription_plans sp ON sp.id=fp.subscription_plan_id AND sp.status='active'
         WHERE fp.merchant_user_id=? AND fp.status='published'
           AND fp.moderation_status NOT IN ('hidden','removed'){$whereCursor}
         ORDER BY fp.created_at DESC,fp.public_id DESC
         LIMIT " . ($scanLimit + 1),
        $params
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $processCount = min(count($rows), $scanLimit);
    $items = [];
    $lastScanned = null;
    $cursorRow = null;
    $hasMore = false;

    for ($index = 0; $index < $processCount; $index++) {
        $post = $rows[$index];
        $lastScanned = $post;
        if (!mg_social_can_view($pdo, $post, $viewerId, $socialContext)) continue;
        $items[] = [
            'id' => (string)$post['public_id'],
            'type' => (string)$post['post_type'],
            'headline' => $post['headline'] !== null ? (string)$post['headline'] : null,
            'body' => $post['body'] !== null ? (string)$post['body'] : null,
            'media' => mg_public_profile_post_media($post['media_json'] ?? null),
            'visibility' => (string)$post['visibility'],
            'published_at' => (string)$post['created_at'],
            'product_id' => $post['product_public_id'] !== null ? (string)$post['product_public_id'] : null,
            'subscription_plan_id' => $post['plan_public_id'] !== null ? (string)$post['plan_public_id'] : null,
            'engagement' => [
                'comments' => (int)$post['comment_count'],
                'reactions' => (int)$post['reaction_count'],
                'shares' => (int)$post['share_count'],
            ],
        ];
        if (count($items) >= $limit) {
            $cursorRow = $post;
            $hasMore = $index < ($processCount - 1) || count($rows) > $scanLimit;
            break;
        }
    }

    if ($cursorRow === null && count($rows) > $scanLimit && $lastScanned !== null) {
        $cursorRow = $lastScanned;
        $hasMore = true;
    }

    $next = $hasMore && $cursorRow !== null
        ? mg_public_profile_cursor_encode(['kind' => 'post', 'time' => (string)$cursorRow['created_at'], 'id' => (string)$cursorRow['public_id']])
        : null;

    return ['items' => $items, 'next_cursor' => $next, 'has_more' => $hasMore, 'limit' => $limit];
}

function mg_public_profile_plans(PDO $pdo, int $ownerId, ?string $cursor, int $limit): array
{
    $decoded = mg_public_profile_cursor_decode($cursor);
    $params = [$ownerId];
    $whereCursor = '';
    if ($decoded !== null) {
        if (($decoded['kind'] ?? null) !== 'plan' || !isset($decoded['time'], $decoded['id'])) {
            throw new InvalidArgumentException('Invalid pagination cursor.');
        }
        $time = (string)$decoded['time'];
        $id = (string)$decoded['id'];
        if (strtotime($time) === false || preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) {
            throw new InvalidArgumentException('Invalid pagination cursor.');
        }
        $whereCursor = ' AND (created_at<? OR (created_at=? AND public_id<?))';
        array_push($params, $time, $time, $id);
    }

    $rows = mg_public_profile_query(
        $pdo,
        "SELECT public_id,name,description,amount_cents,currency,interval_unit,interval_count,trial_days,created_at
         FROM subscription_plans
         WHERE owner_user_id=? AND status='active'{$whereCursor}
         ORDER BY created_at DESC,public_id DESC
         LIMIT " . ($limit + 1),
        $params
    )->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    $items = array_map(static fn(array $row): array => [
        'id' => (string)$row['public_id'],
        'name' => (string)$row['name'],
        'description' => $row['description'] !== null ? (string)$row['description'] : null,
        'amount_cents' => (int)$row['amount_cents'],
        'currency' => (string)$row['currency'],
        'interval' => ['unit' => (string)$row['interval_unit'], 'count' => (int)$row['interval_count']],
        'trial' => ['days' => (int)$row['trial_days']],
    ], $rows);

    $next = null;
    if ($hasMore && $rows !== []) {
        $last = $rows[array_key_last($rows)];
        $next = mg_public_profile_cursor_encode(['kind' => 'plan', 'time' => (string)$last['created_at'], 'id' => (string)$last['public_id']]);
    }
    return ['items' => $items, 'next_cursor' => $next, 'has_more' => $hasMore, 'limit' => $limit];
}

function mg_public_profile_counts(PDO $pdo, int $ownerId, ?array $storefront): array
{
    $stmt = mg_public_profile_query(
        $pdo,
        "SELECT
           (SELECT COUNT(*) FROM social_follows WHERE followed_user_id=? AND status='active') AS followers,
           (SELECT COUNT(DISTINCT subscriber_user_id) FROM subscriptions
             WHERE recipient_user_id=? AND recovery_status='clear'
               AND status IN ('trialing','active','cancel_pending') AND current_period_end>NOW()) AS supporters",
        [$ownerId, $ownerId]
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['followers' => 0, 'supporters' => 0];

    $products = 0;
    if ($storefront !== null) {
        if ($storefront['_revision_id'] !== null) {
            $products = (int)mg_public_profile_query(
                $pdo,
                "SELECT COUNT(*)
                 FROM merchant_storefront_revision_products rp
                 INNER JOIN catalog_products cp ON cp.id=rp.catalog_product_id
                 INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
                 WHERE rp.storefront_revision_id=? AND rp.visibility='visible'
                   AND cp.merchant_user_id=? AND cp.status='published' AND cpv.version_status='published'",
                [(int)$storefront['_revision_id'], $ownerId]
            )->fetchColumn();
        } else {
            $products = (int)mg_public_profile_query(
                $pdo,
                "SELECT COUNT(*) FROM catalog_products cp
                 INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
                 WHERE cp.merchant_user_id=? AND cp.status='published' AND cpv.version_status='published'",
                [$ownerId]
            )->fetchColumn();
        }
    }

    return [
        'followers' => (int)$row['followers'],
        'supporters' => (int)$row['supporters'],
        'published_products' => $products,
    ];
}

function mg_public_profile_tip(PDO $pdo, array $profile, ?int $viewerId): array
{
    if ($viewerId !== null && $viewerId === (int)$profile['user_id']) return ['available' => false];
    try {
        mg_public_profile_query_count_tick(2);
        return mg_tip_public_profile_capability($pdo, (string)$profile['public_id'], $viewerId);
    } catch (Throwable) {
        return ['available' => false];
    }
}

function mg_public_profile_read(PDO $pdo, string $slug, array $options = []): array
{
    mg_public_profile_query_count_reset();
    $slug = mg_public_profile_slug($slug);
    $viewerId = isset($options['viewer_id']) ? (int)$options['viewer_id'] : null;
    $viewerId = mg_public_profile_viewer_is_active($pdo, $viewerId);
    $preview = !empty($options['preview']);
    $profile = mg_public_profile_load($pdo, $slug);
    $access = mg_public_profile_assert_access($pdo, $profile, $viewerId, $preview);
    $ownerId = (int)$profile['user_id'];

    mg_public_profile_query_count_tick($viewerId !== null && $viewerId !== $ownerId ? 2 : 0);
    $socialContext = mg_social_view_context($pdo, $viewerId, $ownerId);
    $storefront = mg_public_profile_storefront($pdo, $ownerId);

    $productLimit = mg_public_profile_limit($options['product_limit'] ?? MG_PUBLIC_PROFILE_DEFAULT_LIMIT);
    $postLimit = mg_public_profile_limit($options['post_limit'] ?? MG_PUBLIC_PROFILE_DEFAULT_LIMIT);
    $planLimit = mg_public_profile_limit($options['plan_limit'] ?? MG_PUBLIC_PROFILE_DEFAULT_LIMIT);

    $publicStorefront = $storefront;
    if ($publicStorefront !== null) unset($publicStorefront['_id'], $publicStorefront['_revision_id']);

    return [
        'profile' => mg_public_profile_identity($profile, $access),
        'links' => mg_public_profile_links($pdo, (int)$profile['id']),
        'sections' => mg_public_profile_sections($pdo, (int)$profile['id']),
        'storefront' => $publicStorefront,
        'products' => mg_public_profile_products($pdo, $ownerId, $storefront, $options['product_cursor'] ?? null, $productLimit),
        'posts' => mg_public_profile_posts($pdo, $ownerId, $viewerId, $socialContext, $options['post_cursor'] ?? null, $postLimit),
        'subscription_plans' => mg_public_profile_plans($pdo, $ownerId, $options['plan_cursor'] ?? null, $planLimit),
        'tip' => mg_public_profile_tip($pdo, $profile, $viewerId),
        'social_counts' => mg_public_profile_counts($pdo, $ownerId, $storefront),
    ];
}

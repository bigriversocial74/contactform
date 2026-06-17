<?php
declare(strict_types=1);

require_once __DIR__ . '/_public_profile.php';

const MG_PROFILE_DISCOVERY_DEFAULT_LIMIT = 18;
const MG_PROFILE_DISCOVERY_MAX_LIMIT = 36;
const MG_PROFILE_DISCOVERY_SECTION_LIMIT = 6;

function mg_profile_discovery_text(mixed $value, int $max = 120): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (mb_strlen($value) > $max) throw new InvalidArgumentException('Invalid search filters.');
    return $value;
}

function mg_profile_discovery_limit(mixed $value): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => MG_PROFILE_DISCOVERY_DEFAULT_LIMIT]]);
    return max(1, min((int)$limit, MG_PROFILE_DISCOVERY_MAX_LIMIT));
}

function mg_profile_discovery_like(string $value): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
}

function mg_profile_discovery_cursor_encode(array $payload): string
{
    return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
}

function mg_profile_discovery_cursor_decode(?string $cursor, string $signature): ?array
{
    $cursor = trim((string)$cursor);
    if ($cursor === '') return null;
    if (strlen($cursor) > 900 || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) throw new InvalidArgumentException('Invalid pagination cursor.');
    $padding = (4 - (strlen($cursor) % 4)) % 4;
    $decoded = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
    if (!is_string($decoded) || strlen($decoded) > 700) throw new InvalidArgumentException('Invalid pagination cursor.');
    $payload = json_decode($decoded, true);
    if (!is_array($payload) || !hash_equals($signature, (string)($payload['signature'] ?? ''))) throw new InvalidArgumentException('Invalid pagination cursor.');
    if (!isset($payload['relevance'], $payload['featured'], $payload['activity'], $payload['id'])) throw new InvalidArgumentException('Invalid pagination cursor.');
    if (!is_numeric($payload['relevance']) || !is_numeric($payload['featured']) || strtotime((string)$payload['activity']) === false) throw new InvalidArgumentException('Invalid pagination cursor.');
    if (preg_match('/^[a-f0-9-]{36}$/', (string)$payload['id']) !== 1) throw new InvalidArgumentException('Invalid pagination cursor.');
    return $payload;
}

function mg_profile_discovery_filters(array $input): array
{
    $query = mb_strtolower(mg_profile_discovery_text($input['q'] ?? '', 100));
    $type = mb_strtolower(mg_profile_discovery_text($input['type'] ?? '', 40));
    $location = mb_strtolower(mg_profile_discovery_text($input['location'] ?? '', 100));
    $category = mb_strtolower(mg_profile_discovery_text($input['category'] ?? '', 60));
    if ($type !== '' && preg_match('/^[a-z0-9_-]+$/', $type) !== 1) throw new InvalidArgumentException('Invalid search filters.');
    if ($category !== '' && preg_match('/^[a-z0-9 _-]+$/', $category) !== 1) throw new InvalidArgumentException('Invalid search filters.');
    return compact('query', 'type', 'location', 'category');
}

function mg_profile_discovery_signature(array $filters): string
{
    return hash('sha256', json_encode($filters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_profile_discovery_viewer(PDO $pdo): ?array
{
    return mg_public_profile_session_viewer($pdo);
}

function mg_profile_discovery_base_sql(array $filters, ?int $viewerId, array &$params): string
{
    $query = $filters['query'];
    if ($query === '') {
        $relevance = '0';
    } else {
        $exact = $query;
        $prefix = mg_profile_discovery_like($query) . '%';
        $contains = '%' . mg_profile_discovery_like($query) . '%';
        $relevance = "(CASE
          WHEN LOWER(pp.slug)=? THEN 900
          WHEN LOWER(pp.slug) LIKE ? ESCAPE '!' THEN 760
          WHEN LOWER(pp.display_name)=? THEN 700
          WHEN LOWER(pp.display_name) LIKE ? ESCAPE '!' THEN 620
          WHEN LOWER(COALESCE(pp.headline,'')) LIKE ? ESCAPE '!' THEN 420
          WHEN LOWER(COALESCE(pp.location_label,'')) LIKE ? ESCAPE '!' THEN 260
          WHEN LOWER(pp.profile_type)=? THEN 180
          ELSE 100 END)";
        array_push($params, $exact, $prefix, $exact, $prefix, $contains, $contains, $exact);
    }

    $sql = "SELECT
      pp.public_id,pp.slug,pp.display_name,pp.headline,pp.avatar_url,pp.location_label,
      pp.profile_type,pp.visibility,pp.published_at,pp.updated_at,
      {$relevance} AS relevance_score,
      (SELECT COUNT(*) FROM social_follows sf WHERE sf.followed_user_id=pp.user_id AND sf.status='active') AS follower_count,
      (SELECT COUNT(DISTINCT s.subscriber_user_id) FROM subscriptions s
        WHERE s.recipient_user_id=pp.user_id AND s.recovery_status='clear'
          AND s.status IN ('trialing','active','cancel_pending') AND s.current_period_end>NOW()) AS supporter_count,
      (SELECT COUNT(*) FROM catalog_products cp
        INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id
        WHERE cp.merchant_user_id=pp.user_id AND cp.status='published' AND cpv.version_status='published') AS published_product_count,
      EXISTS(SELECT 1 FROM merchant_storefronts ms WHERE ms.merchant_user_id=pp.user_id AND ms.status='published') AS has_published_storefront,
      GREATEST(
        COALESCE(pp.updated_at,'1970-01-01 00:00:00'),
        COALESCE((SELECT MAX(fp.created_at) FROM feed_posts fp
          WHERE fp.created_by_user_id=pp.user_id AND fp.status='published'
            AND fp.moderation_status NOT IN ('hidden','removed') AND fp.visibility IN ('public','unlisted')),'1970-01-01 00:00:00')
      ) AS recent_activity,
      (
        EXISTS(SELECT 1 FROM merchant_storefronts ms2 WHERE ms2.merchant_user_id=pp.user_id AND ms2.status='published') * 1000000
        + LEAST((SELECT COUNT(*) FROM catalog_products cp2
            INNER JOIN catalog_product_versions cpv2 ON cpv2.id=cp2.current_version_id
            WHERE cp2.merchant_user_id=pp.user_id AND cp2.status='published' AND cpv2.version_status='published'),999) * 1000
        + LEAST((SELECT COUNT(*) FROM social_follows sf2 WHERE sf2.followed_user_id=pp.user_id AND sf2.status='active'),999)
      ) AS featured_score
    FROM public_profiles pp
    INNER JOIN users u ON u.id=pp.user_id
    WHERE u.status='active' AND pp.status='active' AND pp.visibility IN ('public','unlisted')";

    if ($query !== '') {
        $contains = '%' . mg_profile_discovery_like($query) . '%';
        $sql .= " AND (LOWER(pp.display_name) LIKE ? ESCAPE '!'
          OR LOWER(pp.slug) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(pp.headline,'')) LIKE ? ESCAPE '!'
          OR LOWER(COALESCE(pp.location_label,'')) LIKE ? ESCAPE '!'
          OR LOWER(pp.profile_type) LIKE ? ESCAPE '!')";
        array_push($params, $contains, $contains, $contains, $contains, $contains);
    }
    if ($filters['type'] !== '') {
        $sql .= ' AND LOWER(pp.profile_type)=?';
        $params[] = $filters['type'];
    }
    if ($filters['location'] !== '') {
        $sql .= " AND LOWER(COALESCE(pp.location_label,'')) LIKE ? ESCAPE '!'";
        $params[] = '%' . mg_profile_discovery_like($filters['location']) . '%';
    }
    if ($filters['category'] !== '') {
        $sql .= " AND EXISTS(SELECT 1 FROM catalog_products cpc
          INNER JOIN catalog_product_versions cpvc ON cpvc.id=cpc.current_version_id
          WHERE cpc.merchant_user_id=pp.user_id AND cpc.status='published' AND cpvc.version_status='published'
            AND (LOWER(cpc.product_type)=? OR LOWER(cpvc.title) LIKE ? ESCAPE '!' OR LOWER(COALESCE(cpvc.description,'')) LIKE ? ESCAPE '!'))";
        $categoryContains = '%' . mg_profile_discovery_like($filters['category']) . '%';
        array_push($params, $filters['category'], $categoryContains, $categoryContains);
    }
    if ($viewerId !== null) {
        $sql .= ' AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=pp.user_id) OR (sb.blocking_user_id=pp.user_id AND sb.blocked_user_id=?))';
        array_push($params, $viewerId, $viewerId);
    }
    return $sql;
}

function mg_profile_discovery_item(array $row, string $resultKind = 'organic'): array
{
    return [
        'id' => (string)$row['public_id'],
        'slug' => (string)$row['slug'],
        'display_name' => (string)$row['display_name'],
        'headline' => $row['headline'] !== null ? (string)$row['headline'] : null,
        'avatar_url' => mg_public_profile_safe_url($row['avatar_url'] ?? null, true),
        'location' => $row['location_label'] !== null ? (string)$row['location_label'] : null,
        'profile_type' => (string)$row['profile_type'],
        'visibility' => (string)$row['visibility'],
        'url' => '/profile.php?slug=' . rawurlencode((string)$row['slug']),
        'audience' => ['followers' => (int)$row['follower_count'], 'supporters' => (int)$row['supporter_count']],
        'published_products' => (int)$row['published_product_count'],
        'has_published_storefront' => (bool)$row['has_published_storefront'],
        'result_kind' => $resultKind,
    ];
}

function mg_profile_discovery_search(PDO $pdo, array $input, ?int $viewerId): array
{
    $filters = mg_profile_discovery_filters($input);
    $limit = mg_profile_discovery_limit($input['limit'] ?? MG_PROFILE_DISCOVERY_DEFAULT_LIMIT);
    $signature = mg_profile_discovery_signature($filters);
    $cursor = mg_profile_discovery_cursor_decode(isset($input['cursor']) ? (string)$input['cursor'] : null, $signature);
    $params = [];
    $base = mg_profile_discovery_base_sql($filters, $viewerId, $params);
    $sql = 'SELECT * FROM (' . $base . ') discovery WHERE 1=1';
    if ($cursor !== null) {
        $sql .= ' AND (relevance_score<? OR (relevance_score=? AND featured_score<?) OR (relevance_score=? AND featured_score=? AND recent_activity<?) OR (relevance_score=? AND featured_score=? AND recent_activity=? AND public_id>?))';
        array_push($params,
            (int)$cursor['relevance'],
            (int)$cursor['relevance'], (int)$cursor['featured'],
            (int)$cursor['relevance'], (int)$cursor['featured'], (string)$cursor['activity'],
            (int)$cursor['relevance'], (int)$cursor['featured'], (string)$cursor['activity'], (string)$cursor['id']
        );
    }
    $sql .= ' ORDER BY relevance_score DESC,featured_score DESC,recent_activity DESC,public_id ASC LIMIT ' . ($limit + 1);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    $items = array_map(static fn(array $row): array => mg_profile_discovery_item($row), $rows);
    $next = null;
    if ($hasMore && $rows !== []) {
        $last = $rows[array_key_last($rows)];
        $next = mg_profile_discovery_cursor_encode([
            'signature' => $signature,
            'relevance' => (int)$last['relevance_score'],
            'featured' => (int)$last['featured_score'],
            'activity' => (string)$last['recent_activity'],
            'id' => (string)$last['public_id'],
        ]);
    }
    return ['items' => $items, 'next_cursor' => $next, 'has_more' => $hasMore, 'limit' => $limit, 'filters' => $filters];
}

function mg_profile_discovery_section(PDO $pdo, string $kind, ?int $viewerId, int $limit = MG_PROFILE_DISCOVERY_SECTION_LIMIT): array
{
    $params = [];
    $base = mg_profile_discovery_base_sql(['query' => '', 'type' => '', 'location' => '', 'category' => ''], $viewerId, $params);
    $order = match ($kind) {
        'recent' => 'recent_activity DESC,featured_score DESC,public_id ASC',
        'storefronts' => 'has_published_storefront DESC,published_product_count DESC,featured_score DESC,public_id ASC',
        default => 'featured_score DESC,recent_activity DESC,public_id ASC',
    };
    $where = $kind === 'storefronts' ? ' WHERE has_published_storefront=1 OR published_product_count>0' : '';
    $stmt = $pdo->prepare('SELECT * FROM (' . $base . ') discovery' . $where . ' ORDER BY ' . $order . ' LIMIT ' . max(1, min($limit, 12)));
    $stmt->execute($params);
    return array_map(static fn(array $row): array => mg_profile_discovery_item($row, 'curated'), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function mg_profile_discovery_read(PDO $pdo, array $input, ?int $viewerId): array
{
    $results = mg_profile_discovery_search($pdo, $input, $viewerId);
    $includeSections = empty($input['cursor']) && trim((string)($input['q'] ?? '')) === '';
    return [
        'results' => $results,
        'sections' => $includeSections ? [
            'featured' => mg_profile_discovery_section($pdo, 'featured', $viewerId),
            'recent' => mg_profile_discovery_section($pdo, 'recent', $viewerId),
            'storefronts' => mg_profile_discovery_section($pdo, 'storefronts', $viewerId),
        ] : ['featured' => [], 'recent' => [], 'storefronts' => []],
        'policy' => ['organic_and_curated_are_separate' => true, 'private_behavioral_or_payment_data_used' => false],
    ];
}

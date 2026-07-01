<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

const MG_STORIES_MAX_VIDEO_SECONDS = 30;
const MG_STORIES_IMAGE_MAX_BYTES = 8388608;
const MG_STORIES_VIDEO_MAX_BYTES = 52428800;
const MG_STORIES_DEFAULT_LIMIT = 32;

function mg_stories_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) return false;
    static $cache = [];
    $database = '';
    try { $database = (string)($pdo->query('SELECT DATABASE()')->fetchColumn() ?: ''); } catch (Throwable) { $database = ''; }
    $key = spl_object_id($pdo) . '|' . $database . '|' . strtolower($table);
    if (array_key_exists($key, $cache)) return $cache[$key];
    if ($database !== '') {
        try { $stmt = $pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1'); $stmt->execute([$database, $table]); if ($stmt->fetchColumn()) return $cache[$key] = true; } catch (Throwable) {}
    }
    try { $quoted = $pdo->quote($table); if (is_string($quoted) && $quoted !== '') { $stmt = $pdo->query('SHOW TABLES LIKE ' . $quoted); if ($stmt && $stmt->fetchColumn()) return $cache[$key] = true; } } catch (Throwable) {}
    try { $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 0'); return $cache[$key] = true; } catch (Throwable) { return $cache[$key] = false; }
}

function mg_stories_schema_status(PDO $pdo): array
{
    $tables = ['microgifter_stories' => mg_stories_table_exists($pdo, 'microgifter_stories'), 'microgifter_story_views' => mg_stories_table_exists($pdo, 'microgifter_story_views')];
    return ['ready' => !in_array(false, $tables, true), 'tables' => $tables];
}

function mg_stories_require_schema(PDO $pdo): void
{
    $status = mg_stories_schema_status($pdo);
    if (!$status['ready']) throw new RuntimeException('Feed Stories setup is incomplete. Run database/microgifter_feed_stories.sql on the active database.');
}

function mg_stories_text(mixed $value, int $max, string $default = ''): string
{
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', trim((string)$value)) ?? '';
    if ($text === '') $text = $default;
    return function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($text) > $max ? mb_substr($text, 0, $max) : (strlen($text) > $max ? substr($text, 0, $max) : $text);
}

function mg_stories_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    $url = trim((string)$value);
    if ($url === '' || strlen($url) > 700 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) return $url;
    return filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $url) === 1 ? $url : null;
}

function mg_stories_public_id(mixed $value): string
{
    $id = strtolower(trim((string)$value));
    if (preg_match('/^[a-f0-9-]{36}$/', $id) !== 1) throw new InvalidArgumentException('Invalid identifier.');
    return $id;
}

function mg_stories_uuid(): string
{
    return function_exists('mg_public_uuid') ? mg_public_uuid() : bin2hex(random_bytes(16));
}

function mg_stories_viewer_user(): ?array
{
    try { $user = mg_refresh_session_user(); return is_array($user) ? $user : null; } catch (Throwable) { $user = mg_current_user(); return is_array($user) ? $user : null; }
}

function mg_stories_viewer_session_key(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $sid = session_id();
    if (!is_string($sid) || $sid === '') $sid = (string)(mg_client_ip() ?? 'anonymous') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    return hash('sha256', $sid);
}

function mg_stories_user_can_admin(array $user): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    return in_array('admin', $roles, true) || in_array('super_admin', $roles, true) || (function_exists('mg_api_user_has_permission') && (mg_api_user_has_permission($user, 'admin.access') || mg_api_user_has_permission($user, 'ads.review')));
}

function mg_stories_user_can_merchant(array $user, PDO $pdo): bool
{
    if (function_exists('mg_user_has_merchant_access') && mg_user_has_merchant_access($user, $pdo)) return true;
    if (mg_stories_user_can_admin($user)) return true;
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (in_array('merchant', $roles, true)) return true;
    return function_exists('mg_api_user_has_permission') && (mg_api_user_has_permission($user, 'merchant.manage') || mg_api_user_has_permission($user, 'merchant.campaigns.view') || mg_api_user_has_permission($user, 'catalog.products.view'));
}

function mg_stories_profile(PDO $pdo, int $userId): array
{
    $profile = ['id' => (string)$userId, 'display_name' => 'Microgifter member', 'avatar_url' => null, 'profile_type' => 'profile', 'url' => '#'];
    if (mg_stories_table_exists($pdo, 'public_profiles')) {
        try {
            $stmt = $pdo->prepare('SELECT public_id,slug,display_name,avatar_url,profile_type FROM public_profiles WHERE user_id=? LIMIT 1');
            $stmt->execute([$userId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $profile['id'] = (string)($row['public_id'] ?? $userId);
                $profile['display_name'] = mg_stories_text($row['display_name'] ?? '', 140, 'Microgifter member');
                $profile['avatar_url'] = mg_stories_safe_url($row['avatar_url'] ?? null, true);
                $profile['profile_type'] = mg_stories_text($row['profile_type'] ?? 'profile', 40, 'profile');
                $slug = trim((string)($row['slug'] ?? ''));
                $profile['url'] = $slug !== '' ? '/profile.php?slug=' . rawurlencode($slug) : '#';
                return $profile;
            }
        } catch (Throwable) {}
    }
    try { $stmt = $pdo->prepare('SELECT display_name,full_name,email FROM users WHERE id=? LIMIT 1'); $stmt->execute([$userId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); if (is_array($row)) $profile['display_name'] = mg_stories_text($row['display_name'] ?? $row['full_name'] ?? $row['email'] ?? '', 140, 'Microgifter member'); } catch (Throwable) {}
    return $profile;
}

function mg_stories_product_url(array $product): string
{
    $id = (string)($product['public_id'] ?? ''); $slug = trim((string)($product['slug'] ?? ''));
    return $id !== '' ? '/product.php?id=' . rawurlencode($id) . ($slug !== '' ? '&p=' . rawurlencode($slug) : '') : '/feed.php';
}

function mg_stories_campaign_url(array $campaign): string
{
    $slug = trim((string)($campaign['public_slug'] ?? '')); $id = trim((string)($campaign['public_id'] ?? ''));
    if ($slug !== '') return '/campaign.php?slug=' . rawurlencode($slug);
    return $id !== '' ? '/campaign.php?c=' . rawurlencode($id) : '/merchant-campaigns.php';
}

function mg_stories_product_for_merchant(PDO $pdo, int $merchantId, string $publicId): ?array
{
    if (!mg_stories_table_exists($pdo, 'catalog_products') || !mg_stories_table_exists($pdo, 'catalog_product_versions')) return null;
    $stmt = $pdo->prepare("SELECT p.id,p.public_id,p.slug,p.status,p.product_type,v.title,v.description,v.unit_value_cents,v.currency FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.public_id=? AND p.merchant_user_id=? AND p.status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_stories_campaign_for_merchant(PDO $pdo, int $merchantId, string $publicId): ?array
{
    if (!mg_stories_table_exists($pdo, 'campaigns')) return null;
    $stmt = $pdo->prepare("SELECT id,public_id,public_slug,title,description,campaign_type,status,starts_at,ends_at FROM campaigns WHERE public_id=? AND merchant_user_id=? AND status<>'archived' LIMIT 1");
    $stmt->execute([$publicId, $merchantId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function mg_stories_asset_for_owner(PDO $pdo, int $ownerId, string $assetPublicId): array
{
    if (!mg_stories_table_exists($pdo, 'catalog_assets')) throw new RuntimeException('Media assets are unavailable.');
    $stmt = $pdo->prepare("SELECT id,public_id,asset_type,storage_provider,storage_key,mime_type,byte_size,duration_ms,status FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND status='ready' LIMIT 1");
    $stmt->execute([$assetPublicId, $ownerId]); $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($asset)) throw new RuntimeException('Story media is not available.');
    if (!in_array((string)$asset['asset_type'], ['image','video'], true)) throw new RuntimeException('Stories support image or video media only.');
    return $asset;
}

function mg_stories_project(PDO $pdo, array $row, ?int $viewerId, string $viewerSessionKey): array
{
    $storyId = (int)$row['id']; $viewed = false; $views = 0;
    try { if ($viewerId !== null) { $stmt = $pdo->prepare('SELECT 1 FROM microgifter_story_views WHERE story_id=? AND viewer_user_id=? LIMIT 1'); $stmt->execute([$storyId, $viewerId]); } else { $stmt = $pdo->prepare('SELECT 1 FROM microgifter_story_views WHERE story_id=? AND viewer_session_id=? LIMIT 1'); $stmt->execute([$storyId, $viewerSessionKey]); } $viewed = (bool)$stmt->fetchColumn(); } catch (Throwable) {}
    try { $stmt = $pdo->prepare('SELECT COUNT(*) FROM microgifter_story_views WHERE story_id=?'); $stmt->execute([$storyId]); $views = (int)$stmt->fetchColumn(); } catch (Throwable) {}
    $ownerId = (int)$row['owner_user_id']; $owner = mg_stories_profile($pdo, $ownerId);
    $linked = ['type' => (string)$row['linked_type'], 'id' => null, 'title' => null, 'url' => $row['cta_url'] ?: null];
    if ((string)$row['linked_type'] === 'product' && !empty($row['linked_product_id']) && mg_stories_table_exists($pdo, 'catalog_products')) {
        try { $stmt = $pdo->prepare('SELECT p.public_id,p.slug,p.status,p.product_type,v.title FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.id=? LIMIT 1'); $stmt->execute([(int)$row['linked_product_id']]); $product = $stmt->fetch(PDO::FETCH_ASSOC); if (is_array($product)) $linked = ['type' => 'product', 'id' => (string)$product['public_id'], 'title' => (string)($product['title'] ?? 'Product'), 'url' => mg_stories_product_url($product)]; } catch (Throwable) {}
    } elseif ((string)$row['linked_type'] === 'campaign' && !empty($row['linked_campaign_id']) && mg_stories_table_exists($pdo, 'campaigns')) {
        try { $stmt = $pdo->prepare('SELECT public_id,public_slug,title,status,campaign_type FROM campaigns WHERE id=? LIMIT 1'); $stmt->execute([(int)$row['linked_campaign_id']]); $campaign = $stmt->fetch(PDO::FETCH_ASSOC); if (is_array($campaign)) $linked = ['type' => 'campaign', 'id' => (string)$campaign['public_id'], 'title' => (string)($campaign['title'] ?? 'Campaign'), 'url' => mg_stories_campaign_url($campaign)]; } catch (Throwable) {}
    }
    $ctaUrl = mg_stories_safe_url($row['cta_url'] ?? '', true) ?? ($linked['url'] ?? null);
    return ['id' => (string)$row['public_id'], 'story_type' => (string)$row['story_type'], 'media_type' => (string)$row['media_type'], 'media_url' => mg_stories_safe_url($row['media_url'] ?? '', true), 'thumbnail_url' => mg_stories_safe_url($row['thumbnail_url'] ?? '', true), 'caption' => $row['caption'] !== null ? (string)$row['caption'] : '', 'cta_label' => $row['cta_label'] !== null ? (string)$row['cta_label'] : '', 'cta_url' => $ctaUrl, 'linked' => $linked, 'owner' => $owner, 'created_at' => (string)$row['created_at'], 'expires_at' => (string)$row['expires_at'], 'viewed' => $viewed, 'view_count' => $views, 'permissions' => ['is_owner' => $viewerId !== null && $viewerId === $ownerId, 'can_delete' => $viewerId !== null && $viewerId === $ownerId]];
}

function mg_stories_list(PDO $pdo, ?int $viewerId, string $viewerSessionKey, int $limit = MG_STORIES_DEFAULT_LIMIT): array
{
    mg_stories_require_schema($pdo); $limit = max(1, min(80, $limit));
    $stmt = $pdo->prepare("SELECT * FROM microgifter_stories WHERE status='active' AND expires_at>NOW() ORDER BY created_at DESC,id DESC LIMIT {$limit}");
    $stmt->execute();
    return array_map(static fn(array $row): array => mg_stories_project($pdo, $row, $viewerId, $viewerSessionKey), $stmt->fetchAll(PDO::FETCH_ASSOC));
}

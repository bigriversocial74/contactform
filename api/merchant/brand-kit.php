<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';

function mg_brand_user_can(array $user, string $permission): bool
{
    $roles = is_array($user['roles'] ?? null) ? $user['roles'] : [];
    if (array_intersect($roles, ['merchant', 'admin', 'super_admin'])) return true;
    $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    return in_array($permission, $permissions, true) || in_array('merchant.manage', $permissions, true);
}

function mg_brand_require(array $user, string $permission): void
{
    if (!mg_brand_user_can($user, $permission)) mg_fail('Permission denied.', 403);
}

function mg_brand_clean_text(mixed $value, string $fallback, int $max): string
{
    $text = trim((string) $value);
    if ($text === '') $text = $fallback;
    $text = preg_replace('/\s+/', ' ', $text) ?: $fallback;
    return mb_substr($text, 0, $max);
}

function mg_brand_json(mixed $value, int $maxBytes = 262144): ?string
{
    if ($value === null || $value === '' || $value === []) return null;
    if (!is_array($value)) mg_fail('Brand kit payload must be an object.', 422);
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || strlen($json) > $maxBytes) mg_fail('Brand kit payload is too large.', 422);
    return $json;
}

function mg_brand_public_url(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') mg_fail('Website URL is required.', 422);
    if (!preg_match('#^https?://#i', $raw)) $raw = 'https://' . $raw;
    if (mb_strlen($raw) > 1000) mg_fail('Website URL is too long.', 422);
    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) mg_fail('Invalid website URL.', 422);
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) mg_fail('Only http and https website URLs can be scanned.', 422);
    $host = strtolower((string) $parts['host']);
    $ips = @gethostbynamel($host) ?: [];
    if (!$ips) mg_fail('Website host could not be resolved.', 422);
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            mg_fail('Website scanner cannot access private or reserved network addresses.', 422);
        }
    }
    return $raw;
}

function mg_brand_absolute_url(string $base, string $url): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '' || str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:')) return null;
    if (preg_match('#^https?://#i', $url)) return $url;
    $baseParts = parse_url($base);
    if (!is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) return null;
    if (str_starts_with($url, '//')) return $baseParts['scheme'] . ':' . $url;
    $origin = $baseParts['scheme'] . '://' . $baseParts['host'] . (!empty($baseParts['port']) ? ':' . $baseParts['port'] : '');
    if (str_starts_with($url, '/')) return $origin . $url;
    $path = (string) ($baseParts['path'] ?? '/');
    $dir = preg_replace('#/[^/]*$#', '/', $path) ?: '/';
    return $origin . $dir . $url;
}

function mg_brand_fetch_url(string $url, int $limit = 700000): string
{
    $url = mg_brand_public_url($url);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 7,
            'ignore_errors' => true,
            'user_agent' => 'MicrogifterDesignStudioScanner/1.0',
            'header' => "Accept: text/html,text/css;q=0.9,*/*;q=0.5\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $content = @file_get_contents($url, false, $context, 0, $limit);
    if (!is_string($content) || $content === '') mg_fail('Unable to scan website.', 422);
    return $content;
}

function mg_brand_normalize_color(string $color): ?string
{
    $color = strtolower(trim($color));
    if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $color)) return null;
    if (strlen($color) === 4) {
        $color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
    }
    return $color;
}

function mg_brand_extract_colors(string $content): array
{
    preg_match_all('/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/', $content, $matches);
    $counts = [];
    foreach ($matches[0] ?? [] as $match) {
        $color = mg_brand_normalize_color($match);
        if (!$color) continue;
        if (in_array($color, ['#ffffff', '#000000', '#eeeeee', '#f8f8f8', '#f9f9f9'], true)) continue;
        $counts[$color] = ($counts[$color] ?? 0) + 1;
    }
    arsort($counts);
    return array_slice(array_keys($counts), 0, 8);
}

function mg_brand_scan_html(string $url, string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $title = '';
    $titleNode = $xpath->query('//title')->item(0);
    if ($titleNode) $title = mg_brand_clean_text($titleNode->textContent, '', 180);

    $logos = [];
    $images = [];
    $social = [];
    $stylesheets = [];

    foreach ($xpath->query('//meta[@property or @name]') as $node) {
        $key = strtolower((string) ($node->attributes->getNamedItem('property')?->nodeValue ?? $node->attributes->getNamedItem('name')?->nodeValue ?? ''));
        $value = (string) ($node->attributes->getNamedItem('content')?->nodeValue ?? '');
        if (in_array($key, ['og:image', 'twitter:image', 'twitter:image:src'], true)) {
            $absolute = mg_brand_absolute_url($url, $value);
            if ($absolute) $images[] = ['url' => $absolute, 'role' => 'social_image', 'source' => $key];
        }
    }

    foreach ($xpath->query('//link[@href]') as $node) {
        $rel = strtolower((string) ($node->attributes->getNamedItem('rel')?->nodeValue ?? ''));
        $href = (string) ($node->attributes->getNamedItem('href')?->nodeValue ?? '');
        $absolute = mg_brand_absolute_url($url, $href);
        if (!$absolute) continue;
        if (str_contains($rel, 'icon')) $logos[] = ['url' => $absolute, 'role' => str_contains($rel, 'apple') ? 'logo' : 'favicon', 'source' => 'link:' . $rel];
        if (str_contains($rel, 'stylesheet') && count($stylesheets) < 4) $stylesheets[] = $absolute;
    }

    foreach ($xpath->query('//img[@src]') as $node) {
        $src = (string) ($node->attributes->getNamedItem('src')?->nodeValue ?? '');
        $absolute = mg_brand_absolute_url($url, $src);
        if (!$absolute) continue;
        $text = strtolower((string) ($node->attributes->getNamedItem('alt')?->nodeValue ?? '') . ' ' . (string) ($node->attributes->getNamedItem('class')?->nodeValue ?? '') . ' ' . (string) ($node->attributes->getNamedItem('id')?->nodeValue ?? ''));
        $role = str_contains($text, 'logo') || str_contains($absolute, 'logo') ? 'logo' : 'product_image';
        $candidate = ['url' => $absolute, 'role' => $role, 'source' => 'img'];
        if ($role === 'logo') $logos[] = $candidate;
        if (count($images) < 16) $images[] = $candidate;
    }

    foreach ($xpath->query('//a[@href]') as $node) {
        $href = (string) ($node->attributes->getNamedItem('href')?->nodeValue ?? '');
        $lower = strtolower($href);
        foreach (['instagram','facebook','linkedin','tiktok','twitter','x.com','youtube'] as $network) {
            if (str_contains($lower, $network) && count($social) < 12) {
                $social[$network] = $href;
            }
        }
    }

    $colors = mg_brand_extract_colors($html);
    foreach ($stylesheets as $sheet) {
        try {
            $css = mg_brand_fetch_url($sheet, 200000);
            $colors = array_values(array_unique(array_merge($colors, mg_brand_extract_colors($css))));
        } catch (Throwable) {
        }
        if (count($colors) >= 8) break;
    }
    $colors = array_slice($colors, 0, 8);

    $logos = array_values(array_slice(array_unique($logos, SORT_REGULAR), 0, 8));
    $images = array_values(array_slice(array_unique($images, SORT_REGULAR), 0, 16));

    return [
        'title' => $title,
        'logo_candidates' => $logos,
        'image_candidates' => $images,
        'palette' => $colors,
        'social' => $social,
        'primary_color' => $colors[0] ?? null,
        'secondary_color' => $colors[1] ?? null,
        'accent_color' => $colors[2] ?? null,
    ];
}

function mg_brand_format(array $row): array
{
    return [
        'id' => (string) $row['public_id'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'source_url' => $row['source_url'] ?? null,
        'logo_url' => $row['logo_url'] ?? null,
        'primary_color' => $row['primary_color'] ?? null,
        'secondary_color' => $row['secondary_color'] ?? null,
        'accent_color' => $row['accent_color'] ?? null,
        'palette' => json_decode((string) ($row['palette_json'] ?? ''), true) ?: [],
        'fonts' => json_decode((string) ($row['font_json'] ?? ''), true) ?: [],
        'social' => json_decode((string) ($row['social_json'] ?? ''), true) ?: [],
        'image_candidates' => json_decode((string) ($row['image_candidates_json'] ?? ''), true) ?: [],
        'scan_result' => json_decode((string) ($row['scan_result_json'] ?? ''), true) ?: [],
        'scan_status' => (string) $row['scan_status'],
        'scanned_at' => $row['scanned_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

$user = mg_require_api_user();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$pdo = mg_db();
$workspace = mg_merchant_ensure_workspace($pdo, $user);
$workspaceId = (int) $workspace['id'];
$userId = (int) $user['id'];

if ($method === 'GET') {
    mg_brand_require($user, 'merchant.brand_kits.view');
    $stmt = $pdo->prepare("SELECT * FROM merchant_brand_kits WHERE workspace_id=? AND status <> 'archived' ORDER BY FIELD(status,'active','draft'), updated_at DESC LIMIT 20");
    $stmt->execute([$workspaceId]);
    mg_ok(['items' => array_map('mg_brand_format', $stmt->fetchAll()), 'workspace_id' => (string) $workspace['public_id']]);
}

if ($method !== 'POST') mg_fail('Method not allowed.', 405);
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string) ($input['action'] ?? 'scan_website')));
mg_brand_require($user, 'merchant.brand_kits.manage');

if ($action === 'scan_website') {
    $websiteUrl = mg_brand_public_url((string) ($input['website_url'] ?? ''));
    $html = mg_brand_fetch_url($websiteUrl);
    $scan = mg_brand_scan_html($websiteUrl, $html);
    $name = mg_brand_clean_text($input['name'] ?? ($scan['title'] ?: $workspace['display_name'] ?? 'Merchant Brand Kit'), 'Merchant Brand Kit', 180);
    $kitId = trim((string) ($input['id'] ?? ''));
    $logoUrl = $scan['logo_candidates'][0]['url'] ?? ($scan['image_candidates'][0]['url'] ?? null);
    $paletteJson = mg_brand_json($scan['palette']);
    $socialJson = mg_brand_json($scan['social']);
    $imageJson = mg_brand_json($scan['image_candidates']);
    $scanJson = mg_brand_json($scan);

    if ($kitId !== '') {
        $lookup = $pdo->prepare('SELECT id FROM merchant_brand_kits WHERE public_id=? AND workspace_id=? LIMIT 1');
        $lookup->execute([$kitId, $workspaceId]);
        $existing = $lookup->fetch();
        if (!$existing) mg_fail('Brand kit not found.', 404);
        $dbId = (int) $existing['id'];
        $pdo->prepare("UPDATE merchant_brand_kits SET name=?,source_url=?,logo_url=?,primary_color=?,secondary_color=?,accent_color=?,palette_json=?,social_json=?,image_candidates_json=?,scan_result_json=?,scan_status='scanned',scanned_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
            ->execute([$name, $websiteUrl, $logoUrl, $scan['primary_color'], $scan['secondary_color'], $scan['accent_color'], $paletteJson, $socialJson, $imageJson, $scanJson, $userId, $dbId]);
        $pdo->prepare('DELETE FROM merchant_brand_kit_assets WHERE brand_kit_id=? AND status="candidate"')->execute([$dbId]);
    } else {
        $kitId = mg_merchant_uuid();
        $pdo->prepare("INSERT INTO merchant_brand_kits (public_id,workspace_id,merchant_user_id,name,status,source_url,logo_url,primary_color,secondary_color,accent_color,palette_json,social_json,image_candidates_json,scan_result_json,scan_status,scanned_at,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'scanned',NOW(),?,?,NOW(),NOW())")
            ->execute([$kitId, $workspaceId, $userId, $name, 'draft', $websiteUrl, $logoUrl, $scan['primary_color'], $scan['secondary_color'], $scan['accent_color'], $paletteJson, $socialJson, $imageJson, $scanJson, $userId, $userId]);
        $dbId = (int) $pdo->lastInsertId();
    }

    $assetInsert = $pdo->prepare('INSERT INTO merchant_brand_kit_assets (public_id,brand_kit_id,asset_role,source_url,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,"candidate",?,NOW(),NOW())');
    $candidates = array_slice(array_merge($scan['logo_candidates'], $scan['image_candidates']), 0, 20);
    $seen = [];
    foreach ($candidates as $candidate) {
        $candidateUrl = (string) ($candidate['url'] ?? '');
        if ($candidateUrl === '' || isset($seen[$candidateUrl])) continue;
        $seen[$candidateUrl] = true;
        $assetInsert->execute([mg_merchant_uuid(), $dbId, (string) ($candidate['role'] ?? 'other'), $candidateUrl, mg_brand_json($candidate)]);
    }

    $fresh = $pdo->prepare('SELECT * FROM merchant_brand_kits WHERE public_id=? LIMIT 1');
    $fresh->execute([$kitId]);
    mg_audit('merchant.brand_kit_scanned', 'merchant_brand_kit', ['brand_kit_id' => $kitId, 'source_url' => $websiteUrl], $userId);
    mg_ok(['brand_kit' => mg_brand_format($fresh->fetch())], 'Brand kit scanned.', 201);
}

if ($action === 'save_brand_kit') {
    $kitId = trim((string) ($input['id'] ?? ''));
    if ($kitId === '') mg_fail('Brand kit is required.', 422);
    $lookup = $pdo->prepare('SELECT id FROM merchant_brand_kits WHERE public_id=? AND workspace_id=? LIMIT 1');
    $lookup->execute([$kitId, $workspaceId]);
    $existing = $lookup->fetch();
    if (!$existing) mg_fail('Brand kit not found.', 404);
    $name = mg_brand_clean_text($input['name'] ?? 'Merchant Brand Kit', 'Merchant Brand Kit', 180);
    $palette = is_array($input['palette'] ?? null) ? array_values(array_filter(array_map('mg_brand_normalize_color', $input['palette']))) : [];
    $primary = mg_brand_normalize_color((string) ($input['primary_color'] ?? ($palette[0] ?? ''))) ?: null;
    $secondary = mg_brand_normalize_color((string) ($input['secondary_color'] ?? ($palette[1] ?? ''))) ?: null;
    $accent = mg_brand_normalize_color((string) ($input['accent_color'] ?? ($palette[2] ?? ''))) ?: null;
    $pdo->prepare("UPDATE merchant_brand_kits SET name=?,status=?,logo_url=?,primary_color=?,secondary_color=?,accent_color=?,palette_json=?,font_json=?,social_json=?,scan_status=IF(scan_status='not_started','approved',scan_status),approved_at=IF(?='active',COALESCE(approved_at,NOW()),approved_at),approved_by_user_id=IF(?='active',?,approved_by_user_id),updated_by_user_id=?,updated_at=NOW() WHERE id=?")
        ->execute([
            $name,
            in_array((string) ($input['status'] ?? 'active'), ['draft','active','archived'], true) ? (string) $input['status'] : 'active',
            trim((string) ($input['logo_url'] ?? '')) ?: null,
            $primary,
            $secondary,
            $accent,
            mg_brand_json($palette),
            mg_brand_json(is_array($input['fonts'] ?? null) ? $input['fonts'] : []),
            mg_brand_json(is_array($input['social'] ?? null) ? $input['social'] : []),
            (string) ($input['status'] ?? 'active'),
            (string) ($input['status'] ?? 'active'),
            $userId,
            $userId,
            (int) $existing['id'],
        ]);
    $fresh = $pdo->prepare('SELECT * FROM merchant_brand_kits WHERE id=? LIMIT 1');
    $fresh->execute([(int) $existing['id']]);
    mg_audit('merchant.brand_kit_saved', 'merchant_brand_kit', ['brand_kit_id' => $kitId], $userId);
    mg_ok(['brand_kit' => mg_brand_format($fresh->fetch())], 'Brand kit saved.');
}

mg_fail('Unsupported brand kit action.', 422);

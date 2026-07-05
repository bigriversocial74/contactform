<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app.php';
require_once dirname(__DIR__, 2) . '/api/db.php';

function mg_blog_statuses(): array
{
    return ['draft', 'published', 'scheduled', 'archived'];
}

function mg_blog_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable) {
        return false;
    }
}

function mg_blog_schema_ready(PDO $pdo): array
{
    $missing = [];
    foreach (['blog_posts', 'blog_categories', 'blog_tags', 'blog_post_tags'] as $table) {
        if (!mg_blog_table_exists($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return ['ready' => $missing === [], 'missing' => $missing];
}

function mg_blog_slugify(string $value, string $fallback = 'post'): string
{
    $value = trim(strtolower($value));
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }
    $value = trim(preg_replace('/[^a-z0-9]+/', '-', $value) ?? '', '-');
    return $value !== '' ? substr($value, 0, 180) : $fallback;
}

function mg_blog_unique_slug(PDO $pdo, string $slug, int $excludeId = 0): string
{
    $base = mg_blog_slugify($slug);
    $candidate = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM blog_posts WHERE slug=? AND deleted_at IS NULL';
        $params = [$candidate];
        if ($excludeId > 0) {
            $sql .= ' AND id<>?';
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            return $candidate;
        }
        $candidate = substr($base, 0, 172) . '-' . $i++;
    }
}

function mg_blog_datetime_input_to_sql(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    try {
        return (new DateTimeImmutable(str_replace('T', ' ', $value)))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function mg_blog_sql_to_datetime_input(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function mg_blog_format_date(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Unpublished';
    }
    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function mg_blog_excerpt(string $body, int $limit = 170): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
    if ($text === '') {
        return '';
    }
    return mb_strlen($text) <= $limit
        ? $text
        : rtrim(mb_substr($text, 0, $limit - 1), " \t\n\r\0\x0B.,;:") . '…';
}

function mg_blog_reading_time(string $body): int
{
    $words = preg_split('/\s+/', trim(strip_tags($body))) ?: [];
    return max(1, (int)ceil(count(array_filter($words)) / 220));
}

function mg_blog_allowed_html(string $html): string
{
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a><img><figure><figcaption><code><pre><hr>');
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
    $html = preg_replace('/\s+(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '', $html) ?? $html;
    return trim($html);
}

function mg_blog_categories(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT id,name,slug,description,sort_order,is_active FROM blog_categories' . ($activeOnly ? ' WHERE is_active=1' : '') . ' ORDER BY sort_order ASC,name ASC';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_blog_category_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id,name,slug,description,sort_order,is_active FROM blog_categories WHERE slug=? AND is_active=1 LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_blog_public_post_url(array $post): string
{
    return '/blog-post.php?slug=' . rawurlencode((string)($post['slug'] ?? ''));
}

function mg_blog_public_category_url(array $category): string
{
    return '/blog-category.php?slug=' . rawurlencode((string)($category['slug'] ?? ''));
}

function mg_blog_public_where(array $filters, array &$params): array
{
    $where = ["p.status='published'", 'p.deleted_at IS NULL', '(p.published_at IS NULL OR p.published_at <= NOW())'];
    if (!empty($filters['category_slug'])) {
        $where[] = 'c.slug=?';
        $params[] = (string)$filters['category_slug'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.body LIKE ?)';
        $needle = '%' . (string)$filters['search'] . '%';
        array_push($params, $needle, $needle, $needle);
    }
    if (!empty($filters['featured'])) {
        $where[] = 'p.is_featured=1';
    }
    return $where;
}

function mg_blog_list_public_posts(PDO $pdo, array $filters = []): array
{
    $params = [];
    $where = mg_blog_public_where($filters, $params);
    $limit = max(1, min(60, (int)($filters['limit'] ?? 12)));
    $offset = max(0, (int)($filters['offset'] ?? 0));
    $sql = 'SELECT p.*,c.name AS category_name,c.slug AS category_slug,u.display_name AS author_display_name,u.full_name AS author_full_name,u.email AS author_email FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.is_featured DESC, COALESCE(p.published_at,p.created_at) DESC, p.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_blog_count_public_posts(PDO $pdo, array $filters = []): int
{
    $params = [];
    $where = mg_blog_public_where($filters, $params);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function mg_blog_get_public_post(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare("SELECT p.*,c.name AS category_name,c.slug AS category_slug,u.display_name AS author_display_name,u.full_name AS author_full_name,u.email AS author_email FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=? AND p.status='published' AND p.deleted_at IS NULL AND (p.published_at IS NULL OR p.published_at <= NOW()) LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_blog_get_admin_post(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT p.*,c.name AS category_name,c.slug AS category_slug,u.display_name AS author_display_name,u.full_name AS author_full_name,u.email AS author_email FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE p.id=? AND p.deleted_at IS NULL LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function mg_blog_admin_posts(PDO $pdo, array $filters = []): array
{
    $where = ['p.deleted_at IS NULL'];
    $params = [];
    if (!empty($filters['status']) && in_array((string)$filters['status'], mg_blog_statuses(), true)) {
        $where[] = 'p.status=?';
        $params[] = (string)$filters['status'];
    }
    if (!empty($filters['category_id'])) {
        $where[] = 'p.category_id=?';
        $params[] = (int)$filters['category_id'];
    }
    if (!empty($filters['search'])) {
        $where[] = '(p.title LIKE ? OR p.slug LIKE ? OR p.excerpt LIKE ?)';
        $needle = '%' . (string)$filters['search'] . '%';
        array_push($params, $needle, $needle, $needle);
    }
    $limit = max(1, min(100, (int)($filters['limit'] ?? 50)));
    $sql = 'SELECT p.id,p.title,p.slug,p.excerpt,p.status,p.featured_image,p.is_featured,p.published_at,p.updated_at,c.name AS category_name,u.display_name AS author_display_name,u.full_name AS author_full_name,u.email AS author_email FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.updated_at DESC,p.id DESC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mg_blog_status_counts(PDO $pdo): array
{
    $counts = ['all' => 0, 'draft' => 0, 'published' => 0, 'scheduled' => 0, 'archived' => 0];
    foreach (($pdo->query('SELECT status,COUNT(*) total FROM blog_posts WHERE deleted_at IS NULL GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $status = (string)$row['status'];
        $total = (int)$row['total'];
        if (isset($counts[$status])) {
            $counts[$status] = $total;
            $counts['all'] += $total;
        }
    }
    return $counts;
}

function mg_blog_tag_names(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT t.name FROM blog_tags t INNER JOIN blog_post_tags pt ON pt.tag_id=t.id WHERE pt.post_id=? ORDER BY t.name ASC');
    $stmt->execute([$postId]);
    return array_values(array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'name')));
}

function mg_blog_sync_tags(PDO $pdo, int $postId, string $csv): void
{
    $tags = [];
    foreach (explode(',', $csv) as $tag) {
        $name = trim(preg_replace('/\s+/', ' ', $tag) ?? '');
        if ($name !== '') {
            $tags[mg_blog_slugify($name, 'tag')] = mb_substr($name, 0, 80);
        }
    }
    $pdo->prepare('DELETE FROM blog_post_tags WHERE post_id=?')->execute([$postId]);
    if (!$tags) {
        return;
    }
    $select = $pdo->prepare('SELECT id FROM blog_tags WHERE slug=? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO blog_tags (name,slug) VALUES (?,?)');
    $link = $pdo->prepare('INSERT IGNORE INTO blog_post_tags (post_id,tag_id) VALUES (?,?)');
    foreach ($tags as $slug => $name) {
        $select->execute([$slug]);
        $id = (int)$select->fetchColumn();
        if ($id < 1) {
            $insert->execute([$name, $slug]);
            $id = (int)$pdo->lastInsertId();
        }
        if ($id > 0) {
            $link->execute([$postId, $id]);
        }
    }
}

function mg_blog_safe_featured_image_url(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > 700 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
        return null;
    }
    if ($value[0] === '/' && !str_starts_with($value, '//')) {
        return $value;
    }
    if (filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1) {
        return $value;
    }
    return null;
}

function mg_blog_featured_storage_key(int $userId, string $assetId, string $extension): string
{
    if ($userId < 1 || preg_match('/^[a-f0-9-]{36}$/i', $assetId) !== 1 || preg_match('/^[a-z0-9]{2,8}$/', $extension) !== 1) {
        throw new InvalidArgumentException('Invalid blog media storage parameters.');
    }
    return mg_storage_normalize_key('blog/featured/' . gmdate('Y/m') . '/user-' . $userId . '/' . str_replace('-', '', strtolower($assetId)) . '.' . $extension);
}

function mg_blog_handle_featured_image_upload(PDO $pdo, string $field, array &$errors, int $userId): ?string
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'Featured image upload failed.';
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    $size = (int)($file['size'] ?? 0);
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'Featured image upload was not valid.';
        return null;
    }
    if ($size < 1 || $size > 5 * 1024 * 1024) {
        $errors[] = 'Featured image must be smaller than 5 MB.';
        return null;
    }
    if (!mg_blog_table_exists($pdo, 'catalog_assets')) {
        $errors[] = 'Media asset storage is not ready. Import the catalog asset migration before uploading blog images.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($tmp));
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        $errors[] = 'Featured image must be JPG, PNG, or WebP.';
        return null;
    }

    $dimensions = @getimagesize($tmp);
    if (!is_array($dimensions)) {
        $errors[] = 'Featured image could not be verified.';
        return null;
    }
    $width = (int)($dimensions[0] ?? 0);
    $height = (int)($dimensions[1] ?? 0);
    if ($width < 1 || $height < 1 || $width > 12000 || $height > 12000 || ($width * $height) > 40000000) {
        $errors[] = 'Featured image dimensions are not allowed.';
        return null;
    }

    $assetId = mg_public_uuid();
    $extension = $allowed[$mime];
    $storageKey = mg_blog_featured_storage_key($userId, $assetId, $extension);
    $absolutePath = null;

    try {
        $absolutePath = mg_storage_store_uploaded_file($tmp, $storageKey);
        $checksum = hash_file('sha256', $absolutePath) ?: null;
        $originalName = preg_replace('/[\x00-\x1F\x7F]+/u', '', basename((string)($file['name'] ?? 'featured.' . $extension))) ?? 'featured.' . $extension;
        $originalName = mb_substr($originalName !== '' ? $originalName : 'featured.' . $extension, 0, 255);
        $metadata = json_encode([
            'source' => 'blog_featured_image',
            'storage_class' => 'persistent',
            'uploaded_at' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO catalog_assets (public_id,owner_user_id,asset_type,storage_provider,storage_key,original_filename,mime_type,byte_size,checksum_sha256,width_px,height_px,status,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'ready',?,NOW(),NOW())");
        $stmt->execute([
            $assetId,
            $userId,
            'image',
            'persistent_local',
            $storageKey,
            $originalName,
            $mime,
            $size,
            $checksum,
            $width,
            $height,
            $metadata,
        ]);

        return mg_storage_asset_public_url($assetId);
    } catch (Throwable $error) {
        if ($absolutePath !== null) {
            try {
                mg_storage_delete_asset_file('persistent_local', $storageKey);
            } catch (Throwable) {
            }
        }
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'blog.featured_image_upload_failed', 'Blog featured image upload failed.', [
                'exception_class' => $error::class,
                'mime_type' => $mime ?? null,
            ], $userId);
        }
        $errors[] = 'Could not save featured image securely.';
        return null;
    }
}

function mg_blog_save_post(PDO $pdo, array $input, array $user, array &$errors): ?int
{
    $id = max(0, (int)($input['id'] ?? 0));
    $existing = $id > 0 ? mg_blog_get_admin_post($pdo, $id) : null;
    if ($id > 0 && !$existing) {
        $errors[] = 'Blog post not found.';
        return null;
    }

    $title = trim((string)($input['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Post title is required.';
    }
    $body = mg_blog_allowed_html((string)($input['body'] ?? ''));
    if ($body === '') {
        $errors[] = 'Post body is required.';
    }
    $status = in_array((string)($input['status'] ?? 'draft'), mg_blog_statuses(), true) ? (string)$input['status'] : 'draft';
    $slug = mg_blog_unique_slug($pdo, trim((string)($input['slug'] ?? '')) ?: $title, $id);
    $excerpt = mb_substr(trim((string)($input['excerpt'] ?? '')) ?: mg_blog_excerpt($body, 220), 0, 500);
    $categoryId = (int)($input['category_id'] ?? 0);
    $categoryId = $categoryId > 0 ? $categoryId : null;
    $seoTitle = mb_substr(trim((string)($input['seo_title'] ?? '')), 0, 180);
    $seoDesc = mb_substr(trim((string)($input['seo_description'] ?? '')), 0, 255);
    $canonical = mb_substr(trim((string)($input['canonical_url'] ?? '')), 0, 255);
    $cta = (string)($input['cta_type'] ?? 'none');
    if (!in_array($cta, ['none', 'merchant_signup', 'investor_page', 'demo_request', 'campaign_builder', 'contact'], true)) {
        $cta = 'none';
    }
    $isFeatured = !empty($input['is_featured']) ? 1 : 0;
    $alt = mb_substr(trim((string)($input['featured_image_alt'] ?? '')), 0, 255);
    $author = max(1, (int)($user['id'] ?? 0));
    $image = trim((string)($existing['featured_image'] ?? ''));
    $upload = mg_blog_handle_featured_image_upload($pdo, 'featured_image_file', $errors, $author);
    if (is_string($upload) && $upload !== '') {
        $image = $upload;
    } elseif (trim((string)($input['featured_image'] ?? '')) !== '') {
        $safeImage = mg_blog_safe_featured_image_url((string)$input['featured_image']);
        if ($safeImage === null) {
            $errors[] = 'Featured image URL must be a safe relative URL or http(s) URL.';
        } else {
            $image = $safeImage;
        }
    }

    $published = mg_blog_datetime_input_to_sql($input['published_at'] ?? null);
    $scheduled = null;
    if ($status === 'published' && $published === null) {
        $published = gmdate('Y-m-d H:i:s');
    }
    if ($status === 'scheduled') {
        $scheduled = $published;
    }
    if ($errors) {
        return null;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare('UPDATE blog_posts SET title=?,slug=?,excerpt=?,body=?,featured_image=?,featured_image_alt=?,status=?,author_id=?,category_id=?,seo_title=?,seo_description=?,canonical_url=?,cta_type=?,is_featured=?,published_at=?,scheduled_at=?,updated_at=NOW() WHERE id=?');
        $stmt->execute([$title, $slug, $excerpt, $body, $image ?: null, $alt ?: null, $status, $author, $categoryId, $seoTitle ?: null, $seoDesc ?: null, $canonical ?: null, $cta, $isFeatured, $published, $scheduled, $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO blog_posts (title,slug,excerpt,body,featured_image,featured_image_alt,status,author_id,category_id,seo_title,seo_description,canonical_url,cta_type,is_featured,published_at,scheduled_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$title, $slug, $excerpt, $body, $image ?: null, $alt ?: null, $status, $author, $categoryId, $seoTitle ?: null, $seoDesc ?: null, $canonical ?: null, $cta, $isFeatured, $published, $scheduled]);
        $id = (int)$pdo->lastInsertId();
    }

    mg_blog_sync_tags($pdo, $id, (string)($input['tags'] ?? ''));
    return $id;
}

function mg_blog_update_post_status(PDO $pdo, int $id, string $status): bool
{
    if ($id < 1 || !in_array($status, mg_blog_statuses(), true)) {
        return false;
    }
    $published = $status === 'published' ? ', published_at=COALESCE(published_at,NOW())' : '';
    $stmt = $pdo->prepare('UPDATE blog_posts SET status=?, updated_at=NOW()' . $published . ' WHERE id=? AND deleted_at IS NULL');
    $stmt->execute([$status, $id]);
    return $stmt->rowCount() > 0;
}

function mg_blog_soft_delete_post(PDO $pdo, int $id): bool
{
    if ($id < 1) {
        return false;
    }
    $stmt = $pdo->prepare("UPDATE blog_posts SET deleted_at=NOW(), status='archived', updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

function mg_blog_author_name(array $post): string
{
    $name = trim((string)($post['author_display_name'] ?? $post['author_full_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }
    $email = trim((string)($post['author_email'] ?? ''));
    return $email !== '' ? $email : 'Microgifter Team';
}

function mg_blog_render_cta(string $type): string
{
    $map = [
        'merchant_signup' => ['Turn future demand into present-day revenue.', 'Use Microgifter to pre-sell products, reward customers, and track claims in one connected platform.', '/pricing.php', 'Explore merchant tools'],
        'investor_page' => ['Microgifter is building Pre Sale Revenue infrastructure.', 'Review the market opportunity and current category thesis for social gifting, local rewards, and demand intelligence.', '/investors.php', 'Open investor page'],
        'demo_request' => ['See Microgifter in action.', 'Explore how hospitality merchants can launch rewards, offers, claims, and customer engagement programs from one system.', '/learn-more.php', 'Book a demo'],
        'campaign_builder' => ['Build a campaign around your best offer.', 'Create products, rewards, and customer recovery campaigns with Microgifter campaign tools.', '/build.php', 'Open builder'],
        'contact' => ['Have a Microgifter question?', 'Start a conversation about merchant growth, local rewards, or investor interest.', '/learn-more.php', 'Contact Microgifter'],
    ];
    if (!isset($map[$type])) {
        return '';
    }
    [$title, $copy, $href, $label] = $map[$type];
    return '<aside class="mg-blog-cta"><div><span>Microgifter</span><h2>' . mg_e($title) . '</h2><p>' . mg_e($copy) . '</p></div><a href="' . mg_e($href) . '">' . mg_e($label) . '</a></aside>';
}

<?php
declare(strict_types=1);

function mg_blog_seed_post_slugs(): array
{
    return [
        'what-is-pre-sale-revenue',
        'how-social-gifting-helps-local-businesses',
        'loyalty-crm-for-hospitality-merchants',
        'why-claim-tracking-matters',
        'the-agentic-gifting-crm',
        'founder-note-building-future-demand-infrastructure',
    ];
}

function mg_blog_showcase_seed_where(array $filters, array &$params): array
{
    $seedSlugs = mg_blog_seed_post_slugs();
    $placeholders = implode(',', array_fill(0, count($seedSlugs), '?'));
    $where = [
        'p.deleted_at IS NULL',
        "p.status='draft'",
        'p.slug IN (' . $placeholders . ')',
    ];
    foreach ($seedSlugs as $slug) {
        $params[] = $slug;
    }
    if (!empty($filters['category_slug'])) {
        $where[] = 'c.slug=?';
        $params[] = (string) $filters['category_slug'];
    }
    return $where;
}

function mg_blog_showcase_posts(PDO $pdo, array $filters = []): array
{
    $publishedTotal = mg_blog_count_public_posts($pdo, $filters);
    if ($publishedTotal > 0) {
        return [
            'posts' => mg_blog_list_public_posts($pdo, $filters),
            'total' => $publishedTotal,
            'mode' => 'published',
        ];
    }

    $params = [];
    $where = mg_blog_showcase_seed_where($filters, $params);
    $limit = max(1, min(60, (int) ($filters['limit'] ?? 12)));
    $offset = max(0, (int) ($filters['offset'] ?? 0));

    $count = $pdo->prepare('SELECT COUNT(*) FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where));
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.display_name AS author_display_name, u.full_name AS author_full_name, u.email AS author_email '
        . 'FROM blog_posts p '
        . 'LEFT JOIN blog_categories c ON c.id=p.category_id '
        . 'LEFT JOIN users u ON u.id=p.author_id '
        . 'WHERE ' . implode(' AND ', $where) . ' '
        . 'ORDER BY p.is_featured DESC, p.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'total' => $total,
        'mode' => 'seed_drafts',
    ];
}

function mg_blog_get_showcase_post(PDO $pdo, string $slug): ?array
{
    $post = mg_blog_get_public_post($pdo, $slug);
    if ($post) {
        return $post;
    }

    if (!in_array($slug, mg_blog_seed_post_slugs(), true)) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.display_name AS author_display_name, u.full_name AS author_full_name, u.email AS author_email FROM blog_posts p LEFT JOIN blog_categories c ON c.id=p.category_id LEFT JOIN users u ON u.id=p.author_id WHERE p.slug=? AND p.status='draft' AND p.deleted_at IS NULL LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

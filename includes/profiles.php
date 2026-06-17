<?php
declare(strict_types=1);

const MG_PROFILE_MAX_LINKS = 12;
const MG_PROFILE_MAX_SECTIONS = 20;

function mg_profile_public_id(string $prefix = 'pp'): string
{
    return $prefix . '_' . bin2hex(random_bytes(12));
}

function mg_profile_allowed_types(): array
{
    return ['customer', 'creator', 'merchant', 'marketing_affiliate'];
}

function mg_profile_allowed_link_types(): array
{
    return ['website', 'shop', 'portfolio', 'social', 'newsletter', 'custom'];
}

function mg_profile_allowed_section_types(): array
{
    return ['about', 'story', 'highlights', 'faq', 'contact', 'custom'];
}

function mg_profile_slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: '';
    $slug = trim($slug, '-');
    return $slug !== '' ? substr($slug, 0, 110) : 'user';
}

function mg_profile_external_url(mixed $value, bool $allowEmpty = true): ?string
{
    $url = trim((string)$value);
    if ($url === '') {
        if ($allowEmpty) return null;
        mg_fail('A valid URL is required.', 422);
    }
    if (strlen($url) > 600 || preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        mg_fail('Invalid URL.', 422);
    }
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower((string)$parts['scheme']), ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
        mg_fail('Only HTTP and HTTPS URLs are allowed.', 422);
    }
    return $url;
}

function mg_profile_media_url(mixed $value): ?string
{
    $url = trim((string)$value);
    if ($url === '') return null;
    if (str_starts_with($url, '/api/public/media.php?asset=') && preg_match('/^\/api\/public\/media\.php\?asset=[a-f0-9-]{36}$/', $url) === 1) {
        return $url;
    }
    return mg_profile_external_url($url);
}

function mg_profile_completion_score(array $profile, array $links = [], array $sections = []): int
{
    $weights = [
        'display_name' => 15,
        'headline' => 15,
        'bio' => 20,
        'avatar_url' => 15,
        'cover_url' => 10,
        'location_label' => 10,
        'website_url' => 5,
    ];
    $score = 0;
    foreach ($weights as $field => $weight) {
        if (trim((string)($profile[$field] ?? '')) !== '') $score += $weight;
    }
    if ($links !== []) $score += 5;
    if ($sections !== []) $score += 5;
    return min(100, $score);
}

function mg_profile_readiness(array $profile, array $links = [], array $sections = []): array
{
    $checks = [
        ['key' => 'identity', 'label' => 'Display name', 'complete' => trim((string)($profile['display_name'] ?? '')) !== '', 'required' => true],
        ['key' => 'slug', 'label' => 'Public profile address', 'complete' => preg_match('/^[a-z0-9](?:[a-z0-9-]{0,108}[a-z0-9])?$/', (string)($profile['slug'] ?? '')) === 1, 'required' => true],
        ['key' => 'headline', 'label' => 'Headline', 'complete' => trim((string)($profile['headline'] ?? '')) !== '', 'required' => true],
        ['key' => 'biography', 'label' => 'Biography', 'complete' => trim((string)($profile['bio'] ?? '')) !== '', 'required' => true],
        ['key' => 'avatar', 'label' => 'Avatar image', 'complete' => trim((string)($profile['avatar_url'] ?? '')) !== '', 'required' => false],
        ['key' => 'cover', 'label' => 'Cover image', 'complete' => trim((string)($profile['cover_url'] ?? '')) !== '', 'required' => false],
        ['key' => 'location', 'label' => 'Location', 'complete' => trim((string)($profile['location_label'] ?? '')) !== '', 'required' => false],
        ['key' => 'links', 'label' => 'At least one active link', 'complete' => count(array_filter($links, static fn(array $link): bool => !empty($link['is_active']))) > 0, 'required' => false],
        ['key' => 'sections', 'label' => 'At least one active section', 'complete' => count(array_filter($sections, static fn(array $section): bool => !empty($section['is_active']))) > 0, 'required' => false],
    ];
    $requiredComplete = true;
    foreach ($checks as $check) {
        if ($check['required'] && !$check['complete']) $requiredComplete = false;
    }
    return [
        'checks' => $checks,
        'required_complete' => $requiredComplete,
        'can_publish' => $requiredComplete && (string)($profile['status'] ?? '') !== 'suspended',
        'score' => mg_profile_completion_score($profile, $links, $sections),
    ];
}

function mg_profile_ensure_for_user(int $userId): array
{
    $pdo = mg_db();
    $stmt = $pdo->prepare('SELECT * FROM public_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    if ($profile) return $profile;

    $userStmt = $pdo->prepare('SELECT id, email, full_name, display_name FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    if (!$user) throw new RuntimeException('User not found for profile creation.');

    $displayName = (string)($user['display_name'] ?: $user['full_name'] ?: $user['email']);
    $slug = mg_profile_unique_slug(mg_profile_slugify($displayName), $userId);
    $insert = $pdo->prepare(
        'INSERT INTO public_profiles (public_id, user_id, slug, display_name, profile_type, visibility, status, completion_score, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([mg_profile_public_id('pp'), $userId, $slug, $displayName, 'customer', 'public', 'draft', 15]);
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: [];
}

function mg_profile_unique_slug(string $baseSlug, int $userId, ?int $profileId = null): string
{
    $pdo = mg_db();
    $baseSlug = mg_profile_slugify($baseSlug ?: ('user-' . $userId));
    $candidate = $baseSlug;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM public_profiles WHERE slug = ?';
        $params = [$candidate];
        if ($profileId) {
            $sql .= ' AND id <> ?';
            $params[] = $profileId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) return $candidate;
        $candidate = substr($baseSlug, 0, 100) . '-' . $i;
        $i++;
    }
}

function mg_profile_links(int $profileId, bool $activeOnly = true): array
{
    $sql = 'SELECT public_id, label, url, link_type, sort_order, is_active FROM public_profile_links WHERE profile_id = ?';
    if ($activeOnly) $sql .= ' AND is_active = 1';
    $sql .= ' ORDER BY sort_order, id';
    $stmt = mg_db()->prepare($sql);
    $stmt->execute([$profileId]);
    return $stmt->fetchAll() ?: [];
}

function mg_profile_sections(int $profileId, bool $activeOnly = true): array
{
    $sql = 'SELECT public_id, section_type, title, body, sort_order, is_active, metadata_json FROM public_profile_sections WHERE profile_id = ?';
    if ($activeOnly) $sql .= ' AND is_active = 1';
    $sql .= ' ORDER BY sort_order, id';
    $stmt = mg_db()->prepare($sql);
    $stmt->execute([$profileId]);
    return $stmt->fetchAll() ?: [];
}

function mg_profile_public_payload(array $profile, bool $includeInactive = false): array
{
    $profileId = (int)$profile['id'];
    $links = mg_profile_links($profileId, !$includeInactive);
    $sections = mg_profile_sections($profileId, !$includeInactive);
    $payload = [
        'public_id' => (string)$profile['public_id'],
        'slug' => (string)$profile['slug'],
        'display_name' => (string)$profile['display_name'],
        'headline' => $profile['headline'] ?? null,
        'bio' => $profile['bio'] ?? null,
        'avatar_url' => $profile['avatar_url'] ?? null,
        'cover_url' => $profile['cover_url'] ?? null,
        'location_label' => $profile['location_label'] ?? null,
        'website_url' => $profile['website_url'] ?? null,
        'profile_type' => (string)$profile['profile_type'],
        'visibility' => (string)$profile['visibility'],
        'status' => (string)$profile['status'],
        'completion_score' => (int)$profile['completion_score'],
        'published_at' => $profile['published_at'] ?? null,
        'updated_at' => $profile['updated_at'] ?? null,
        'links' => $links,
        'sections' => $sections,
    ];
    if ($includeInactive) {
        $payload['readiness'] = mg_profile_readiness($profile, $links, $sections);
        $payload['limits'] = ['links' => MG_PROFILE_MAX_LINKS, 'sections' => MG_PROFILE_MAX_SECTIONS];
        $payload['allowed'] = [
            'profile_types' => mg_profile_allowed_types(),
            'link_types' => mg_profile_allowed_link_types(),
            'section_types' => mg_profile_allowed_section_types(),
        ];
    }
    return $payload;
}

function mg_profile_update(int $userId, array $input): array
{
    $pdo = mg_db();
    $profile = mg_profile_ensure_for_user($userId);
    $profileId = (int)$profile['id'];

    $displayName = trim((string)($input['display_name'] ?? $profile['display_name'] ?? ''));
    $headline = trim((string)($input['headline'] ?? $profile['headline'] ?? ''));
    $bio = trim((string)($input['bio'] ?? $profile['bio'] ?? ''));
    $location = trim((string)($input['location_label'] ?? $profile['location_label'] ?? ''));
    if ($displayName === '' || mb_strlen($displayName) > 120) mg_fail('Display name is required and must be 120 characters or fewer.', 422, ['display_name' => 'Invalid display name.']);
    if (mb_strlen($headline) > 180) mg_fail('Headline must be 180 characters or fewer.', 422, ['headline' => 'Headline is too long.']);
    if (mb_strlen($bio) > 5000) mg_fail('Biography must be 5,000 characters or fewer.', 422, ['bio' => 'Biography is too long.']);
    if (mb_strlen($location) > 160) mg_fail('Location must be 160 characters or fewer.', 422, ['location_label' => 'Location is too long.']);

    $requestedSlug = trim((string)($input['slug'] ?? $profile['slug'] ?? ''));
    $slug = mg_profile_unique_slug($requestedSlug, $userId, $profileId);
    $profileType = trim((string)($input['profile_type'] ?? $profile['profile_type'] ?? 'customer'));
    if (!in_array($profileType, mg_profile_allowed_types(), true)) mg_fail('Invalid profile type.', 422, ['profile_type' => 'Invalid profile type.']);

    $visibility = (string)($input['visibility'] ?? $profile['visibility'] ?? 'public');
    if (!in_array($visibility, ['public', 'private', 'unlisted'], true)) mg_fail('Invalid visibility.', 422, ['visibility' => 'Invalid visibility.']);

    $status = (string)($profile['status'] === 'suspended' ? 'suspended' : ($input['status'] ?? $profile['status'] ?? 'draft'));
    if (!in_array($status, ['draft', 'active', 'hidden', 'suspended'], true)) mg_fail('Invalid status.', 422, ['status' => 'Invalid status.']);

    $website = mg_profile_external_url($input['website_url'] ?? $profile['website_url'] ?? '');
    $avatar = mg_profile_media_url($input['avatar_url'] ?? $profile['avatar_url'] ?? '');
    $cover = mg_profile_media_url($input['cover_url'] ?? $profile['cover_url'] ?? '');
    $updated = [
        'display_name' => $displayName,
        'slug' => $slug,
        'headline' => $headline,
        'bio' => $bio,
        'avatar_url' => $avatar,
        'cover_url' => $cover,
        'location_label' => $location,
        'website_url' => $website,
        'profile_type' => $profileType,
        'visibility' => $visibility,
        'status' => $status,
    ];

    $links = mg_profile_links($profileId, false);
    $sections = mg_profile_sections($profileId, false);
    $readiness = mg_profile_readiness($updated, $links, $sections);
    if ($status === 'active' && !$readiness['can_publish']) {
        mg_fail('Complete the required profile fields before publishing.', 422, ['readiness' => $readiness]);
    }
    $updated['completion_score'] = $readiness['score'];

    $stmt = $pdo->prepare(
        'UPDATE public_profiles
         SET slug = ?, display_name = ?, headline = ?, bio = ?, avatar_url = ?, cover_url = ?, location_label = ?, website_url = ?, profile_type = ?, visibility = ?, status = ?, completion_score = ?, published_at = CASE WHEN ? = "active" AND published_at IS NULL THEN NOW() ELSE published_at END, updated_at = NOW()
         WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([
        $updated['slug'], $updated['display_name'], $updated['headline'], $updated['bio'], $updated['avatar_url'], $updated['cover_url'],
        $updated['location_label'], $updated['website_url'], $updated['profile_type'], $updated['visibility'], $updated['status'],
        $updated['completion_score'], $updated['status'], $profileId, $userId,
    ]);

    mg_audit('profile.updated', 'public_profile', ['profile_id' => $profileId, 'slug' => $updated['slug'], 'status' => $updated['status']], $userId);
    return mg_profile_ensure_for_user($userId);
}

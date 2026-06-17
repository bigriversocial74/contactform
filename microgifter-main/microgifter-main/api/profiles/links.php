<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profiles.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$user = mg_require_api_user();
$userId = (int)$user['id'];
$profile = mg_profile_ensure_for_user($userId);
$profileId = (int)$profile['id'];

if ($method === 'GET') {
    mg_ok(['links' => mg_profile_links($profileId, false), 'limit' => MG_PROFILE_MAX_LINKS], 'Profile links.');
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $links = $input['links'] ?? [];
    if (!is_array($links)) mg_fail('Links must be an array.', 422);
    if (count($links) > MG_PROFILE_MAX_LINKS) mg_fail('A profile can contain at most ' . MG_PROFILE_MAX_LINKS . ' links.', 422);

    $normalized = [];
    foreach ($links as $index => $link) {
        if (!is_array($link)) mg_fail('Each link must be an object.', 422, ['links.' . $index => 'Invalid link.']);
        $label = trim((string)($link['label'] ?? ''));
        $url = trim((string)($link['url'] ?? ''));
        $type = trim((string)($link['link_type'] ?? 'custom'));
        $active = !isset($link['is_active']) || filter_var($link['is_active'], FILTER_VALIDATE_BOOLEAN);
        if ($label === '' && $url === '') continue;
        if ($label === '' || mb_strlen($label) > 120) mg_fail('Every link needs a label of 120 characters or fewer.', 422, ['links.' . $index . '.label' => 'Invalid label.']);
        if (!in_array($type, mg_profile_allowed_link_types(), true)) mg_fail('Invalid profile link type.', 422, ['links.' . $index . '.link_type' => 'Invalid link type.']);
        try {
            $safeUrl = mg_profile_external_url($url, false);
        } catch (Throwable) {
            mg_fail('Every link needs a valid HTTP or HTTPS URL.', 422, ['links.' . $index . '.url' => 'Invalid URL.']);
        }
        $normalized[] = [
            'label' => $label,
            'url' => $safeUrl,
            'link_type' => $type,
            'sort_order' => (count($normalized) + 1) * 10,
            'is_active' => $active ? 1 : 0,
        ];
    }

    $pdo = mg_db();
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM public_profile_links WHERE profile_id = ?');
        $delete->execute([$profileId]);
        $insert = $pdo->prepare(
            'INSERT INTO public_profile_links (public_id, profile_id, label, url, link_type, sort_order, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($normalized as $link) {
            $insert->execute([
                mg_profile_public_id('ppl'), $profileId, $link['label'], $link['url'], $link['link_type'], $link['sort_order'], $link['is_active'],
            ]);
        }
        $profileNow = mg_profile_ensure_for_user($userId);
        $sections = mg_profile_sections($profileId, false);
        $score = mg_profile_completion_score($profileNow, $normalized, $sections);
        $scoreStmt = $pdo->prepare('UPDATE public_profiles SET completion_score = ?, updated_at = NOW() WHERE id = ?');
        $scoreStmt->execute([$score, $profileId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_audit('profile.links_updated', 'public_profile', ['profile_id' => $profileId, 'count' => count($normalized)], $userId);
    mg_ok([
        'links' => mg_profile_links($profileId, false),
        'completion_score' => $score,
        'readiness' => mg_profile_readiness(mg_profile_ensure_for_user($userId), mg_profile_links($profileId, false), mg_profile_sections($profileId, false)),
    ], 'Profile links updated.');
}

mg_fail('Method not allowed.', 405);

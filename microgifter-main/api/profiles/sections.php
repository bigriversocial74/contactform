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
    mg_ok(['sections' => mg_profile_sections($profileId, false), 'limit' => MG_PROFILE_MAX_SECTIONS], 'Profile sections.');
}

if ($method === 'POST') {
    $input = mg_input();
    mg_require_csrf_for_write($input);
    $sections = $input['sections'] ?? [];
    if (!is_array($sections)) mg_fail('Sections must be an array.', 422);
    if (count($sections) > MG_PROFILE_MAX_SECTIONS) mg_fail('A profile can contain at most ' . MG_PROFILE_MAX_SECTIONS . ' sections.', 422);

    $normalized = [];
    foreach ($sections as $index => $section) {
        if (!is_array($section)) mg_fail('Each section must be an object.', 422, ['sections.' . $index => 'Invalid section.']);
        $type = trim((string)($section['section_type'] ?? 'custom'));
        $title = trim((string)($section['title'] ?? ''));
        $body = trim((string)($section['body'] ?? ''));
        $active = !isset($section['is_active']) || filter_var($section['is_active'], FILTER_VALIDATE_BOOLEAN);
        if ($title === '' && $body === '') continue;
        if (!in_array($type, mg_profile_allowed_section_types(), true)) mg_fail('Invalid profile section type.', 422, ['sections.' . $index . '.section_type' => 'Invalid section type.']);
        if (mb_strlen($title) > 160) mg_fail('Section titles must be 160 characters or fewer.', 422, ['sections.' . $index . '.title' => 'Title is too long.']);
        if (mb_strlen($body) > 10000) mg_fail('Section bodies must be 10,000 characters or fewer.', 422, ['sections.' . $index . '.body' => 'Body is too long.']);
        $normalized[] = [
            'section_type' => $type,
            'title' => $title !== '' ? $title : null,
            'body' => $body !== '' ? $body : null,
            'sort_order' => (count($normalized) + 1) * 10,
            'is_active' => $active ? 1 : 0,
        ];
    }

    $pdo = mg_db();
    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM public_profile_sections WHERE profile_id = ?');
        $delete->execute([$profileId]);
        $insert = $pdo->prepare(
            'INSERT INTO public_profile_sections (public_id, profile_id, section_type, title, body, sort_order, is_active, metadata_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($normalized as $section) {
            $insert->execute([
                mg_profile_public_id('pps'), $profileId, $section['section_type'], $section['title'], $section['body'],
                $section['sort_order'], $section['is_active'], null,
            ]);
        }
        $profileNow = mg_profile_ensure_for_user($userId);
        $links = mg_profile_links($profileId, false);
        $score = mg_profile_completion_score($profileNow, $links, $normalized);
        $scoreStmt = $pdo->prepare('UPDATE public_profiles SET completion_score = ?, updated_at = NOW() WHERE id = ?');
        $scoreStmt->execute([$score, $profileId]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    mg_audit('profile.sections_updated', 'public_profile', ['profile_id' => $profileId, 'count' => count($normalized)], $userId);
    mg_ok([
        'sections' => mg_profile_sections($profileId, false),
        'completion_score' => $score,
        'readiness' => mg_profile_readiness(mg_profile_ensure_for_user($userId), mg_profile_links($profileId, false), mg_profile_sections($profileId, false)),
    ], 'Profile sections updated.');
}

mg_fail('Method not allowed.', 405);

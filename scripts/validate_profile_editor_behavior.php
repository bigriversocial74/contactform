<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once dirname(__DIR__) . '/api/bootstrap.php';
require_once dirname(__DIR__) . '/includes/profiles.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function mg_pe_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = mg_db();
$runId = 'profileeditor' . bin2hex(random_bytes(5));
$result = array_fill_keys([
    'profile_created', 'identity_updated', 'readiness_enforced', 'slug_collision_safe',
    'links_sections_scored', 'media_url_safe', 'visibility_transitions', 'rollback_clean',
], false);

$pdo->beginTransaction();
try {
    $ownerId = mg_it_user($pdo, $runId . '-owner@example.test', 'Profile Editor Owner');
    $otherId = mg_it_user($pdo, $runId . '-other@example.test', 'Profile Editor Other');

    $profile = mg_profile_ensure_for_user($ownerId);
    $other = mg_profile_ensure_for_user($otherId);
    mg_pe_assert((int)$profile['user_id'] === $ownerId && (string)$profile['status'] === 'draft', 'Profile foundation was not created.');
    $result['profile_created'] = true;

    $updated = mg_profile_update($ownerId, [
        'display_name' => 'Phoenix Profile Editor',
        'slug' => $runId . '-profile',
        'headline' => 'Local gifting profile',
        'bio' => 'A complete profile used for production behavior validation.',
        'location_label' => 'Phoenix, AZ',
        'website_url' => 'https://example.test/profile',
        'profile_type' => 'creator',
        'visibility' => 'public',
        'status' => 'active',
    ]);
    mg_pe_assert((string)$updated['status'] === 'active' && (string)$updated['slug'] === $runId . '-profile', 'Complete identity did not publish.');
    mg_pe_assert((int)$updated['completion_score'] >= 65, 'Completion score was not updated.');
    $result['identity_updated'] = true;

    $incompleteCandidate = array_merge($other, [
        'display_name' => 'Incomplete Profile',
        'slug' => $runId . '-incomplete',
        'headline' => '',
        'bio' => '',
        'profile_type' => 'customer',
        'visibility' => 'public',
        'status' => 'active',
    ]);
    $incompleteReadiness = mg_profile_readiness($incompleteCandidate, [], []);
    mg_pe_assert($incompleteReadiness['required_complete'] === false, 'Incomplete readiness was accepted.');
    mg_pe_assert($incompleteReadiness['can_publish'] === false, 'Incomplete profile was publishable.');
    $result['readiness_enforced'] = true;

    $otherUpdated = mg_profile_update($otherId, [
        'display_name' => 'Other Complete Profile',
        'slug' => $runId . '-profile',
        'headline' => 'Another complete profile',
        'bio' => 'This profile verifies safe unique slug resolution.',
        'profile_type' => 'customer',
        'visibility' => 'unlisted',
        'status' => 'draft',
    ]);
    mg_pe_assert((string)$otherUpdated['slug'] !== (string)$updated['slug'], 'Slug collision was not resolved safely.');
    mg_pe_assert(str_starts_with((string)$otherUpdated['slug'], $runId . '-profile-'), 'Slug collision used an unexpected address.');
    $result['slug_collision_safe'] = true;

    $profileId = (int)$updated['id'];
    $link = $pdo->prepare('INSERT INTO public_profile_links (public_id,profile_id,label,url,link_type,sort_order,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())');
    $link->execute([mg_profile_public_id('ppl'), $profileId, 'Portfolio', 'https://example.test/work', 'portfolio', 10]);
    $section = $pdo->prepare('INSERT INTO public_profile_sections (public_id,profile_id,section_type,title,body,sort_order,is_active,created_at,updated_at) VALUES (?,?,?,?,?,?,1,NOW(),NOW())');
    $section->execute([mg_profile_public_id('pps'), $profileId, 'about', 'What I make', 'Local gifting products.', 10]);
    $links = mg_profile_links($profileId, false);
    $sections = mg_profile_sections($profileId, false);
    $readiness = mg_profile_readiness($updated, $links, $sections);
    mg_pe_assert(count($links) === 1 && count($sections) === 1, 'Profile collections were not readable.');
    mg_pe_assert((int)$readiness['score'] > (int)$updated['completion_score'], 'Links and sections did not improve completion.');
    $result['links_sections_scored'] = true;

    $assetId = '00000000-0000-4000-8000-' . substr(hash('sha256', $runId), 0, 12);
    $mediaUrl = '/api/public/media.php?asset=' . $assetId;
    mg_pe_assert(mg_profile_media_url($mediaUrl) === $mediaUrl, 'Canonical profile media URL was rejected.');
    mg_pe_assert(preg_match('/^\/api\/public\/media\.php\?asset=[a-f0-9-]{36}$/', $mediaUrl) === 1, 'Canonical media URL shape was invalid.');
    mg_pe_assert(filter_var('https://example.test/avatar.png', FILTER_VALIDATE_URL) !== false, 'Safe external media URL validation failed.');
    $result['media_url_safe'] = true;

    $hidden = mg_profile_update($ownerId, ['status' => 'hidden']);
    mg_pe_assert((string)$hidden['status'] === 'hidden', 'Active profile could not be hidden.');
    $draft = mg_profile_update($ownerId, ['status' => 'draft']);
    mg_pe_assert((string)$draft['status'] === 'draft', 'Hidden profile could not return to draft.');
    $result['visibility_transitions'] = true;

    $pdo->rollBack();
    $result['rollback_clean'] = true;
    echo json_encode($result + ['suite' => 'profile_editing_ui_foundation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

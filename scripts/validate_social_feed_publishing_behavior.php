<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }

require_once dirname(__DIR__) . '/api/social/_publishing.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function sf_assert(bool $condition, string $name): void
{
    if (!$condition) throw new RuntimeException('Social feed validation failed: ' . $name);
}

function sf_expect(callable $callback, string $contains): bool
{
    try { $callback(); } catch (Throwable $error) { return $contains === '' || str_contains($error->getMessage(), $contains); }
    return false;
}

function sf_profile(PDO $pdo, int $userId, string $slug, string $visibility = 'public', string $status = 'active'): array
{
    $now = gmdate('Y-m-d H:i:s');
    $publicId = mg_public_uuid();
    mg_it_insert($pdo, 'public_profiles', [
        'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug,'display_name'=>'Feed ' . $slug,
        'headline'=>'Local feed profile','bio'=>'Feed publishing validation','avatar_url'=>null,'cover_url'=>null,
        'location_label'=>'Phoenix, AZ','website_url'=>null,'profile_type'=>'creator','visibility'=>$visibility,
        'status'=>$status,'completion_score'=>100,'metadata_json'=>json_encode(['private'=>'not projected'], JSON_THROW_ON_ERROR),
        'published_at'=>$status === 'active' ? $now : null,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['public_id'=>$publicId,'slug'=>$slug,'user_id'=>$userId];
}

function sf_input(string $headline, string $visibility = 'public', bool $publish = false): array
{
    return [
        'headline'=>$headline,
        'body'=>'A safe local gifting update for feed validation.',
        'visibility'=>$visibility,
        'post_type'=>'simple',
        'media'=>[['url'=>'https://example.com/' . rawurlencode($headline) . '.jpg','type'=>'image','alt'=>$headline]],
        'publish'=>$publish,
    ];
}

function sf_keys(mixed $value, array &$keys): void
{
    if (!is_array($value)) return;
    foreach ($value as $key=>$child) { $keys[] = strtolower((string)$key); sf_keys($child, $keys); }
}

$pdo = mg_db();
$runId = 'sf' . bin2hex(random_bytes(5));
$result = array_fill_keys([
    'draft_create','publish_transition','idempotent_create','discover_visibility','following_visibility',
    'mute_exclusion','block_exclusion','moderation_exclusion','cursor_stability','owner_filters',
    'update_archive_delete','subscriber_plan_requirement','media_safety','safe_projection','rollback_clean',
], false);

$pdo->beginTransaction();
try {
    $viewer = mg_it_user($pdo, $runId . '-viewer@example.test', 'Feed Viewer');
    $author = mg_it_user($pdo, $runId . '-author@example.test', 'Feed Author');
    $other = mg_it_user($pdo, $runId . '-other@example.test', 'Feed Other');
    $viewerProfile = sf_profile($pdo, $viewer, 'viewer-' . $runId);
    $authorProfile = sf_profile($pdo, $author, 'author-' . $runId);
    $otherProfile = sf_profile($pdo, $other, 'other-' . $runId);

    $draft = mg_publishing_mutate($pdo, $author, 'create', sf_input('Draft ' . $runId));
    $result['draft_create'] = $draft['status'] === 'draft';

    $published = mg_publishing_mutate($pdo, $author, 'publish', sf_input('Published ' . $runId, 'public', true) + ['post_id'=>$draft['id']]);
    $result['publish_transition'] = $published['status'] === 'published' && $published['id'] === $draft['id'];

    $idempotencyKey = 'feed-create:' . $runId;
    $fingerprint = mg_engagement_fingerprint('publishing.create', ['headline'=>'Replay ' . $runId]);
    sf_assert(mg_engagement_claim($pdo, $author, 'publishing.create', $idempotencyKey, $fingerprint) === null, 'idempotency claim');
    $replayPost = mg_publishing_mutate($pdo, $author, 'create', sf_input('Replay ' . $runId, 'public', true));
    mg_engagement_complete($pdo, $author, $idempotencyKey, ['post'=>$replayPost]);
    $replay = mg_engagement_claim($pdo, $author, 'publishing.create', $idempotencyKey, $fingerprint);
    $result['idempotent_create'] = is_array($replay) && !empty($replay['duplicate'])
        && (int)mg_it_scalar($pdo, 'SELECT COUNT(*) FROM feed_posts WHERE public_id=?', [$replayPost['id']]) === 1;

    $followersPost = mg_publishing_mutate($pdo, $author, 'create', sf_input('Followers ' . $runId, 'followers', true));
    $privateDraft = mg_publishing_mutate($pdo, $author, 'create', sf_input('Private ' . $runId, 'private', false));
    $hiddenPost = mg_publishing_mutate($pdo, $other, 'create', sf_input('Hidden ' . $runId, 'public', true));
    $pdo->prepare("UPDATE feed_posts SET moderation_status='hidden' WHERE public_id=?")->execute([$hiddenPost['id']]);
    for ($index=0; $index<4; $index++) mg_publishing_mutate($pdo, $other, 'create', sf_input('Public ' . $index . ' ' . $runId, 'public', true));

    $discover = mg_publishing_feed($pdo, 'discover', null, null, 30);
    $discoverIds = array_column($discover['items'], 'id');
    $result['discover_visibility'] = in_array($published['id'], $discoverIds, true)
        && in_array($replayPost['id'], $discoverIds, true)
        && !in_array($followersPost['id'], $discoverIds, true)
        && !in_array($privateDraft['id'], $discoverIds, true);
    $result['moderation_exclusion'] = !in_array($hiddenPost['id'], $discoverIds, true);

    $now = gmdate('Y-m-d H:i:s');
    mg_it_insert($pdo, 'social_follows', ['follower_user_id'=>$viewer,'followed_user_id'=>$author,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
    $following = mg_publishing_feed($pdo, 'following', $viewer, null, 30);
    $followingIds = array_column($following['items'], 'id');
    $result['following_visibility'] = in_array($followersPost['id'], $followingIds, true);

    mg_it_insert($pdo, 'social_mutes', ['muting_user_id'=>$viewer,'muted_user_id'=>$author,'created_at'=>$now]);
    $muted = mg_publishing_feed($pdo, 'following', $viewer, null, 30);
    $result['mute_exclusion'] = !in_array($followersPost['id'], array_column($muted['items'], 'id'), true);
    $pdo->prepare('DELETE FROM social_mutes WHERE muting_user_id=? AND muted_user_id=?')->execute([$viewer,$author]);

    mg_it_insert($pdo, 'social_blocks', ['blocking_user_id'=>$author,'blocked_user_id'=>$viewer,'created_at'=>$now]);
    $blocked = mg_publishing_feed($pdo, 'following', $viewer, null, 30);
    $result['block_exclusion'] = !in_array($followersPost['id'], array_column($blocked['items'], 'id'), true);
    $pdo->prepare('DELETE FROM social_blocks WHERE blocking_user_id=? AND blocked_user_id=?')->execute([$author,$viewer]);

    $pageOne = mg_publishing_feed($pdo, 'discover', null, null, 2);
    $pageTwo = mg_publishing_feed($pdo, 'discover', null, $pageOne['next_cursor'], 2);
    $result['cursor_stability'] = $pageOne['has_more'] && is_string($pageOne['next_cursor'])
        && array_intersect(array_column($pageOne['items'], 'id'), array_column($pageTwo['items'], 'id')) === [];

    $drafts = mg_publishing_owner_posts($pdo, $author, 'draft', null, 20);
    $publishedOwner = mg_publishing_owner_posts($pdo, $author, 'published', null, 20);
    $result['owner_filters'] = in_array($privateDraft['id'], array_column($drafts['items'], 'id'), true)
        && in_array($published['id'], array_column($publishedOwner['items'], 'id'), true);

    $updated = mg_publishing_mutate($pdo, $author, 'update', sf_input('Updated ' . $runId, 'unlisted') + ['post_id'=>$privateDraft['id']]);
    $archived = mg_publishing_mutate($pdo, $author, 'archive', ['post_id'=>$privateDraft['id']]);
    $deleted = mg_publishing_mutate($pdo, $author, 'delete', ['post_id'=>$privateDraft['id']]);
    $result['update_archive_delete'] = $updated['headline'] === 'Updated ' . $runId && $archived['status'] === 'archived' && $deleted['status'] === 'retired';

    $result['subscriber_plan_requirement'] = sf_expect(
        fn()=>mg_publishing_mutate($pdo, $author, 'create', sf_input('Subscribers ' . $runId, 'subscribers', true)),
        'requires an active subscription plan'
    );
    $safeMedia = mg_publishing_media([
        ['url'=>'javascript:alert(1)','type'=>'image'],
        ['url'=>'https://example.com/safe.jpg','type'=>'image'],
    ]);
    $result['media_safety'] = count($safeMedia) === 1 && $safeMedia[0]['url'] === 'https://example.com/safe.jpg';

    $keys = [];
    sf_keys(['discover'=>$discover,'owner'=>$publishedOwner], $keys);
    $result['safe_projection'] = array_intersect(['user_id','merchant_user_id','created_by_user_id','catalog_product_id','metadata_json','email'], $keys) === [];

    foreach ($result as $name=>$passed) if ($name !== 'rollback_clean') sf_assert($passed, $name);
    $pdo->rollBack();
    $result['rollback_clean'] = true;
    echo json_encode($result + ['suite'=>'social_feed_post_publishing_foundation'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}

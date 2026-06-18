<?php
declare(strict_types=1);

require_once __DIR__ . '/_publishing.php';

$user = mg_require_permission('social.posts.create');
$actorId = (int)$user['id'];
$pdo = mg_db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $scope = strtolower(trim((string)($_GET['scope'] ?? 'legacy')));
    if ($scope === 'mine') {
        mg_rate_limit('social.posts.owner_read', 'user:' . $actorId, 180, 60);
        try {
            $posts = mg_publishing_owner_posts(
                $pdo,
                $actorId,
                strtolower(trim((string)($_GET['status'] ?? ''))),
                isset($_GET['cursor']) ? (string)$_GET['cursor'] : null,
                (int)($_GET['limit'] ?? MG_SOCIAL_OWNER_DEFAULT_LIMIT)
            );
            $profile = mg_publishing_author_profile($pdo, $actorId, false);
            header('Cache-Control: private, no-store, max-age=0');
            mg_ok([
                'posts' => $posts,
                'profile' => [
                    'id' => (string)$profile['public_id'],
                    'slug' => (string)$profile['slug'],
                    'display_name' => (string)$profile['display_name'],
                    'avatar_url' => mg_publishing_safe_url($profile['avatar_url'] ?? null, true),
                    'profile_type' => (string)$profile['profile_type'],
                    'visibility' => (string)$profile['visibility'],
                    'status' => (string)$profile['status'],
                ],
            ]);
        } catch (InvalidArgumentException $error) {
            mg_fail($error->getMessage(), 422);
        } catch (RuntimeException $error) {
            mg_fail($error->getMessage(), 409);
        }
    }

    $after = max(0, (int)($_GET['after_id'] ?? 0));
    $limit = max(1, min((int)($_GET['limit'] ?? 30), 100));
    mg_ok(['posts'=>mg_social_feed($pdo, $actorId, $after, $limit)]);
}

mg_require_method('POST');
$input = mg_input();
mg_require_csrf_for_write($input);
$action = strtolower(trim((string)($input['action'] ?? 'create')));
mg_rate_limit('social.posts.write', 'user:' . $actorId, $action === 'create' ? 40 : 100, 60);

try {
    $key = mg_engagement_key($input);
    $fingerprint = mg_engagement_fingerprint('publishing.' . $action, [
        'post_id' => trim((string)($input['post_id'] ?? '')),
        'headline' => trim((string)($input['headline'] ?? '')),
        'body' => trim((string)($input['body'] ?? '')),
        'visibility' => trim((string)($input['visibility'] ?? 'public')),
        'post_type' => trim((string)($input['post_type'] ?? 'simple')),
        'product_id' => trim((string)($input['product_id'] ?? '')),
        'microgift_id' => trim((string)($input['microgift_id'] ?? '')),
        'subscription_plan_id' => trim((string)($input['subscription_plan_id'] ?? '')),
        'link_url' => trim((string)($input['link_url'] ?? '')),
        'media' => $input['media'] ?? [],
        'publish' => !empty($input['publish']),
    ]);

    $pdo->beginTransaction();
    $replay = mg_engagement_claim($pdo, $actorId, 'publishing.' . $action, $key, $fingerprint);
    if ($replay !== null) {
        $pdo->commit();
        mg_ok($replay, 'Existing post mutation returned.');
    }

    $post = mg_publishing_mutate($pdo, $actorId, $action, $input);
    $result = mg_engagement_complete($pdo, $actorId, $key, ['action'=>$action,'post'=>$post]);
    $pdo->commit();

    mg_audit('social.post_' . $action, 'feed_post', [
        'post_id' => $post['id'],
        'status' => $post['status'],
        'visibility' => $post['visibility'],
        'moderation_status' => $post['moderation_status'],
    ], $actorId);
    mg_event('social.post_' . $action, [
        'post_id' => $post['id'],
        'status' => $post['status'],
        'visibility' => $post['visibility'],
    ], $actorId);
    mg_ok($result, $action === 'create' ? 'Post created.' : 'Post updated.', $action === 'create' ? 201 : 200);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_fail($error->getMessage(), 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error', 'social.post_mutation_failed', 'Social post mutation failed.', [
        'action' => $action,
        'exception_class' => $error::class,
    ], $actorId);
    mg_fail('Unable to update post.', 500);
}

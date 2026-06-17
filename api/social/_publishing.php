<?php
declare(strict_types=1);

require_once __DIR__ . '/_engagement.php';
require_once dirname(__DIR__) . '/profiles/_public_profile.php';

const MG_SOCIAL_FEED_DEFAULT_LIMIT = 18;
const MG_SOCIAL_FEED_MAX_LIMIT = 36;
const MG_SOCIAL_OWNER_DEFAULT_LIMIT = 20;
const MG_SOCIAL_OWNER_MAX_LIMIT = 50;
const MG_SOCIAL_POST_BODY_MAX = 10000;
const MG_SOCIAL_POST_MEDIA_MAX = 8;

function mg_publishing_text(mixed $value, int $max): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (mb_strlen($value) > $max) throw new InvalidArgumentException('Post content is too long.');
    return $value;
}

function mg_publishing_limit(mixed $value, int $default, int $maximum): int
{
    $limit = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['default'=>$default]]);
    return max(1, min((int)$limit, $maximum));
}

function mg_publishing_cursor_encode(array $payload): string
{
    return rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
}

function mg_publishing_cursor_decode(?string $cursor, string $kind): ?array
{
    $cursor = trim((string)$cursor);
    if ($cursor === '') return null;
    if (strlen($cursor) > 900 || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) throw new InvalidArgumentException('Invalid pagination cursor.');
    $padding = (4 - strlen($cursor) % 4) % 4;
    $raw = base64_decode(strtr($cursor . str_repeat('=', $padding), '-_', '+/'), true);
    if (!is_string($raw) || strlen($raw) > 700) throw new InvalidArgumentException('Invalid pagination cursor.');
    $payload = json_decode($raw, true);
    if (!is_array($payload) || ($payload['kind'] ?? null) !== $kind || !isset($payload['time'], $payload['id'])) throw new InvalidArgumentException('Invalid pagination cursor.');
    if (strtotime((string)$payload['time']) === false || preg_match('/^[a-f0-9-]{36}$/i', (string)$payload['id']) !== 1) throw new InvalidArgumentException('Invalid pagination cursor.');
    return $payload;
}

function mg_publishing_safe_url(mixed $value, bool $allowRelative = true): ?string
{
    return mg_public_profile_safe_url($value, $allowRelative);
}

function mg_publishing_media(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : preg_split('/\r?\n/', $raw);
    }
    if (!is_array($raw)) return [];
    $items = array_is_list($raw) ? $raw : [$raw];
    $safe = [];
    foreach ($items as $item) {
        if (count($safe) >= MG_SOCIAL_POST_MEDIA_MAX) break;
        if (is_string($item)) $item = ['url'=>$item];
        if (!is_array($item)) continue;
        $url = mg_publishing_safe_url($item['url'] ?? null, true);
        if ($url === null) continue;
        $type = strtolower(trim((string)($item['type'] ?? 'image')));
        if (!in_array($type, ['image','audio','video','link'], true)) $type = 'link';
        $safe[] = [
            'url'=>$url,
            'type'=>$type,
            'alt'=>isset($item['alt']) ? mb_substr(trim((string)$item['alt']), 0, 240) : null,
            'caption'=>isset($item['caption']) ? mb_substr(trim((string)$item['caption']), 0, 500) : null,
        ];
    }
    return $safe;
}

function mg_publishing_author_profile(PDO $pdo, int $userId, bool $requirePublishable = false): array
{
    $stmt = $pdo->prepare("SELECT pp.id,pp.public_id,pp.slug,pp.display_name,pp.avatar_url,pp.profile_type,pp.visibility,pp.status,u.status user_status
      FROM public_profiles pp INNER JOIN users u ON u.id=pp.user_id WHERE pp.user_id=? LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) throw new RuntimeException('A public profile is required to publish posts.');
    if ($requirePublishable && ((string)$profile['user_status'] !== 'active' || (string)$profile['status'] !== 'active' || !in_array((string)$profile['visibility'], ['public','unlisted'], true))) {
        throw new RuntimeException('Your profile must be active and public or unlisted before publishing.');
    }
    return $profile;
}

function mg_publishing_attachment_ids(PDO $pdo, int $userId, array $input, string $visibility): array
{
    $productId = null; $microgiftId = null; $planId = null;
    $productPublic = trim((string)($input['product_id'] ?? ''));
    if ($productPublic !== '') {
        $stmt = $pdo->prepare("SELECT id FROM catalog_products WHERE public_id=? AND merchant_user_id=? AND status IN ('draft','published') LIMIT 1");
        $stmt->execute([$productPublic, $userId]);
        $productId = (int)($stmt->fetchColumn() ?: 0);
        if ($productId < 1) throw new RuntimeException('Product is not available to this author.');
    }
    $microgiftPublic = trim((string)($input['microgift_id'] ?? ''));
    if ($microgiftPublic !== '') {
        $stmt = $pdo->prepare('SELECT id FROM microgift_instances WHERE public_id=? AND (owner_user_id=? OR issuer_user_id=?) LIMIT 1');
        $stmt->execute([$microgiftPublic, $userId, $userId]);
        $microgiftId = (int)($stmt->fetchColumn() ?: 0);
        if ($microgiftId < 1) throw new RuntimeException('Microgift is not available to this author.');
    }
    $planPublic = trim((string)($input['subscription_plan_id'] ?? ''));
    if ($planPublic !== '') {
        $stmt = $pdo->prepare("SELECT id FROM subscription_plans WHERE public_id=? AND owner_user_id=? AND status='active' LIMIT 1");
        $stmt->execute([$planPublic, $userId]);
        $planId = (int)($stmt->fetchColumn() ?: 0);
        if ($planId < 1) throw new RuntimeException('Subscription plan is not available.');
    }
    if (in_array($visibility, ['subscribers','premium'], true) && $planId === null) throw new InvalidArgumentException('Subscriber content requires an active subscription plan.');
    return [$productId, $microgiftId, $planId];
}

function mg_publishing_payload(PDO $pdo, int $userId, array $input): array
{
    $headline = mg_publishing_text($input['headline'] ?? '', 240);
    $body = trim((string)($input['body'] ?? ''));
    if (mb_strlen($body) > MG_SOCIAL_POST_BODY_MAX) throw new InvalidArgumentException('Post body is too long.');
    $visibility = strtolower(trim((string)($input['visibility'] ?? 'public')));
    if (!in_array($visibility, ['private','unlisted','public','followers','subscribers','premium'], true)) throw new InvalidArgumentException('Invalid post visibility.');
    $postType = strtolower(trim((string)($input['post_type'] ?? 'simple')));
    if (!in_array($postType, ['simple','image','audio','video','greeting_card','multimedia_card','collab'], true)) throw new InvalidArgumentException('Invalid post type.');
    $media = mg_publishing_media($input['media'] ?? []);
    $link = mg_publishing_safe_url($input['link_url'] ?? null, true);
    if ($link !== null && !array_filter($media, static fn(array $item): bool => $item['url'] === $link)) $media[] = ['url'=>$link,'type'=>'link','alt'=>null,'caption'=>null];
    $media = array_slice($media, 0, MG_SOCIAL_POST_MEDIA_MAX);
    if ($headline === '' && $body === '' && $media === []) throw new InvalidArgumentException('Post content is required.');
    [$productId,$microgiftId,$planId] = mg_publishing_attachment_ids($pdo, $userId, $input, $visibility);
    return compact('headline','body','visibility','postType','media','productId','microgiftId','planId');
}

function mg_publishing_post_owned(PDO $pdo, int $userId, string $publicId, bool $forUpdate = false): array
{
    if (preg_match('/^[a-f0-9-]{36}$/i', $publicId) !== 1) throw new InvalidArgumentException('Post is required.');
    $stmt = $pdo->prepare('SELECT * FROM feed_posts WHERE public_id=? AND created_by_user_id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->execute([$publicId, $userId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) throw new RuntimeException('Post is not available.');
    return $post;
}

function mg_publishing_mutate(PDO $pdo, int $userId, string $action, array $input): array
{
    if (!in_array($action, ['create','update','publish','archive','delete'], true)) throw new InvalidArgumentException('Invalid post action.');
    if ($action === 'create') {
        $publish = !empty($input['publish']);
        mg_publishing_author_profile($pdo, $userId, $publish);
        $payload = mg_publishing_payload($pdo, $userId, $input);
        $publicId = mg_public_uuid();
        $status = $publish ? 'published' : 'draft';
        $pdo->prepare("INSERT INTO feed_posts
          (public_id,merchant_user_id,catalog_product_id,linked_microgift_instance_id,subscription_plan_id,current_version_id,post_type,headline,body,media_json,visibility,status,moderation_status,created_by_user_id,created_at,updated_at)
          VALUES (?,?,?,?,?,NULL,?,?,?,?,?,?,'clear',?,NOW(),NOW())")
          ->execute([$publicId,$userId,$payload['productId'],$payload['microgiftId'],$payload['planId'],$payload['postType'],$payload['headline'],$payload['body'],json_encode($payload['media'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$payload['visibility'],$status,$userId]);
        $post = mg_publishing_post_owned($pdo, $userId, $publicId, true);
    } else {
        $publicId = trim((string)($input['post_id'] ?? ''));
        $post = mg_publishing_post_owned($pdo, $userId, $publicId, true);
        if (in_array((string)$post['moderation_status'], ['hidden','removed'], true) && !in_array($action, ['archive','delete'], true)) throw new RuntimeException('Moderated posts cannot be edited or published.');
        if ($action === 'archive' || $action === 'delete') {
            $status = $action === 'archive' ? 'archived' : 'retired';
            $pdo->prepare('UPDATE feed_posts SET status=?,archived_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$status,(int)$post['id']]);
        } else {
            if ($action === 'publish') mg_publishing_author_profile($pdo, $userId, true);
            $payload = mg_publishing_payload($pdo, $userId, $input);
            $status = $action === 'publish' ? 'published' : (string)$post['status'];
            if ($status === 'retired') throw new RuntimeException('Deleted posts cannot be edited.');
            $pdo->prepare('UPDATE feed_posts SET catalog_product_id=?,linked_microgift_instance_id=?,subscription_plan_id=?,post_type=?,headline=?,body=?,media_json=?,visibility=?,status=?,archived_at=NULL,updated_at=NOW() WHERE id=?')
              ->execute([$payload['productId'],$payload['microgiftId'],$payload['planId'],$payload['postType'],$payload['headline'],$payload['body'],json_encode($payload['media'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$payload['visibility'],$status,(int)$post['id']]);
        }
        $post = mg_publishing_post_owned($pdo, $userId, $publicId, true);
    }
    return mg_publishing_owner_project($pdo, $post);
}

function mg_publishing_engagement_state(PDO $pdo, array $post, ?int $viewerId): array
{
    $state = mg_engagement_post_state($pdo, $post, $viewerId);
    $state['saved'] = false;
    if ($viewerId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM feed_post_saves WHERE feed_post_id=? AND user_id=? LIMIT 1');
        $stmt->execute([(int)$post['id'], $viewerId]);
        $state['saved'] = (bool)$stmt->fetchColumn();
    }
    return $state;
}

function mg_publishing_feed_project(PDO $pdo, array $row, ?int $viewerId): array
{
    return [
        'id'=>(string)$row['public_id'],'type'=>(string)$row['post_type'],
        'headline'=>$row['headline']!==null?(string)$row['headline']:null,
        'body'=>$row['body']!==null?(string)$row['body']:null,
        'media'=>mg_publishing_media($row['media_json']??null),'visibility'=>(string)$row['visibility'],
        'published_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at'],
        'author'=>[
            'id'=>(string)$row['profile_public_id'],'slug'=>(string)$row['profile_slug'],
            'display_name'=>(string)($row['profile_display_name']?:$row['author_name']),
            'avatar_url'=>mg_publishing_safe_url($row['avatar_url']??null,true),
            'profile_type'=>(string)$row['profile_type'],'url'=>'/profile.php?slug='.rawurlencode((string)$row['profile_slug']),
        ],
        'attachments'=>[
            'product'=>$row['product_public_id']!==null?['id'=>(string)$row['product_public_id'],'url'=>'/product.php?p='.rawurlencode((string)$row['product_slug'])]:null,
            'microgift_id'=>$row['microgift_public_id']!==null?(string)$row['microgift_public_id']:null,
            'subscription_plan_id'=>$row['plan_public_id']!==null?(string)$row['plan_public_id']:null,
        ],
        'engagement'=>mg_publishing_engagement_state($pdo,$row,$viewerId),
        'permissions'=>['authenticated'=>$viewerId!==null,'is_owner'=>$viewerId!==null&&$viewerId===(int)$row['created_by_user_id'],'can_report'=>$viewerId!==null&&$viewerId!==(int)$row['created_by_user_id']],
    ];
}

function mg_publishing_feed(PDO $pdo, string $mode, ?int $viewerId, ?string $cursor, int $limit): array
{
    if (!in_array($mode, ['discover','following'], true)) throw new InvalidArgumentException('Invalid feed mode.');
    if ($mode === 'following' && $viewerId === null) throw new RuntimeException('Sign in to view your following feed.');
    $limit = mg_publishing_limit($limit, MG_SOCIAL_FEED_DEFAULT_LIMIT, MG_SOCIAL_FEED_MAX_LIMIT);
    $decoded = mg_publishing_cursor_decode($cursor, 'feed:' . $mode);
    $params = [];
    $where = "fp.status='published' AND fp.moderation_status NOT IN ('hidden','removed') AND u.status='active' AND pp.status='active' AND pp.visibility IN ('public','unlisted')";
    if ($mode === 'discover') $where .= " AND fp.visibility IN ('public','unlisted')";
    else {
        $where .= " AND (fp.created_by_user_id=? OR EXISTS(SELECT 1 FROM social_follows sf WHERE sf.follower_user_id=? AND sf.followed_user_id=fp.created_by_user_id AND sf.status='active'))";
        array_push($params, $viewerId, $viewerId);
    }
    if ($viewerId !== null) {
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_mutes sm WHERE sm.muting_user_id=? AND sm.muted_user_id=fp.created_by_user_id)';
        $params[] = $viewerId;
        $where .= ' AND NOT EXISTS(SELECT 1 FROM social_blocks sb WHERE (sb.blocking_user_id=? AND sb.blocked_user_id=fp.created_by_user_id) OR (sb.blocking_user_id=fp.created_by_user_id AND sb.blocked_user_id=?))';
        array_push($params, $viewerId, $viewerId);
    }
    if ($decoded !== null) {
        $where .= ' AND (fp.created_at<? OR (fp.created_at=? AND fp.public_id<?))';
        array_push($params, (string)$decoded['time'], (string)$decoded['time'], (string)$decoded['id']);
    }
    $scanLimit = min(220, max(40, $limit * 6));
    $stmt = $pdo->prepare("SELECT fp.*,u.display_name author_name,
        pp.public_id profile_public_id,pp.slug profile_slug,pp.display_name profile_display_name,pp.avatar_url,pp.profile_type,
        cp.public_id product_public_id,cp.slug product_slug,mi.public_id microgift_public_id,sp.public_id plan_public_id
      FROM feed_posts fp
      INNER JOIN users u ON u.id=fp.created_by_user_id
      INNER JOIN public_profiles pp ON pp.user_id=fp.created_by_user_id
      LEFT JOIN catalog_products cp ON cp.id=fp.catalog_product_id AND cp.status='published'
      LEFT JOIN microgift_instances mi ON mi.id=fp.linked_microgift_instance_id
      LEFT JOIN subscription_plans sp ON sp.id=fp.subscription_plan_id AND sp.status='active'
      WHERE {$where}
      ORDER BY fp.created_at DESC,fp.public_id DESC LIMIT " . ($scanLimit + 1));
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = []; $contexts = []; $cursorRow = null; $processed = 0;
    foreach ($rows as $row) {
        if ($processed >= $scanLimit) break;
        $processed++;
        $authorId = (int)$row['created_by_user_id'];
        if (!isset($contexts[$authorId])) $contexts[$authorId] = mg_social_view_context($pdo, $viewerId, $authorId);
        if (!mg_social_can_view($pdo, $row, $viewerId, $contexts[$authorId])) continue;
        $items[] = mg_publishing_feed_project($pdo, $row, $viewerId);
        if (count($items) >= $limit) { $cursorRow = $row; break; }
    }
    $hasMore = $cursorRow !== null && ($processed < count($rows) || count($rows) > $scanLimit);
    if (!$hasMore && count($rows) > $scanLimit && $processed >= $scanLimit) { $cursorRow = $rows[$scanLimit-1]; $hasMore = true; }
    $next = $hasMore && $cursorRow !== null ? mg_publishing_cursor_encode(['kind'=>'feed:'.$mode,'time'=>(string)$cursorRow['created_at'],'id'=>(string)$cursorRow['public_id']]) : null;
    return ['mode'=>$mode,'items'=>$items,'next_cursor'=>$next,'has_more'=>$hasMore,'limit'=>$limit];
}

function mg_publishing_owner_project(PDO $pdo, array $row): array
{
    $product = null; $microgift = null; $plan = null;
    if ($row['catalog_product_id'] !== null) {
        $stmt=$pdo->prepare('SELECT public_id,slug FROM catalog_products WHERE id=? LIMIT 1'); $stmt->execute([(int)$row['catalog_product_id']]);
        $value=$stmt->fetch(PDO::FETCH_ASSOC); if($value)$product=['id'=>(string)$value['public_id'],'slug'=>(string)$value['slug']];
    }
    if ($row['linked_microgift_instance_id'] !== null) {
        $stmt=$pdo->prepare('SELECT public_id FROM microgift_instances WHERE id=? LIMIT 1'); $stmt->execute([(int)$row['linked_microgift_instance_id']]);
        $value=$stmt->fetchColumn(); if($value)$microgift=(string)$value;
    }
    if ($row['subscription_plan_id'] !== null) {
        $stmt=$pdo->prepare('SELECT public_id FROM subscription_plans WHERE id=? LIMIT 1'); $stmt->execute([(int)$row['subscription_plan_id']]);
        $value=$stmt->fetchColumn(); if($value)$plan=(string)$value;
    }
    return [
        'id'=>(string)$row['public_id'],'type'=>(string)$row['post_type'],'headline'=>$row['headline']!==null?(string)$row['headline']:null,
        'body'=>$row['body']!==null?(string)$row['body']:null,'media'=>mg_publishing_media($row['media_json']??null),
        'visibility'=>(string)$row['visibility'],'status'=>(string)$row['status'],'moderation_status'=>(string)$row['moderation_status'],
        'created_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at'],
        'engagement'=>['comments'=>(int)$row['comment_count'],'reactions'=>(int)$row['reaction_count'],'shares'=>(int)$row['share_count'],'saves'=>(int)$row['save_count']],
        'attachments'=>['product'=>$product,'microgift_id'=>$microgift,'subscription_plan_id'=>$plan],
        'permissions'=>[
            'can_edit'=>(string)$row['status']!=='retired'&&!in_array((string)$row['moderation_status'],['hidden','removed'],true),
            'can_publish'=>(string)$row['status']!=='retired'&&!in_array((string)$row['moderation_status'],['hidden','removed'],true),
            'can_archive'=>!in_array((string)$row['status'],['archived','retired'],true),
            'can_delete'=>(string)$row['status']!=='retired',
        ],
    ];
}

function mg_publishing_owner_posts(PDO $pdo, int $userId, string $status, ?string $cursor, int $limit): array
{
    if (!in_array($status, ['', 'draft','published','archived','retired'], true)) throw new InvalidArgumentException('Invalid post status filter.');
    $limit = mg_publishing_limit($limit, MG_SOCIAL_OWNER_DEFAULT_LIMIT, MG_SOCIAL_OWNER_MAX_LIMIT);
    $decoded = mg_publishing_cursor_decode($cursor, 'owner:' . ($status ?: 'all'));
    $params = [$userId]; $where = 'created_by_user_id=?';
    if ($status !== '') { $where .= ' AND status=?'; $params[] = $status; }
    if ($decoded !== null) { $where .= ' AND (updated_at<? OR (updated_at=? AND public_id<?))'; array_push($params,(string)$decoded['time'],(string)$decoded['time'],(string)$decoded['id']); }
    $stmt = $pdo->prepare("SELECT * FROM feed_posts WHERE {$where} ORDER BY updated_at DESC,public_id DESC LIMIT " . ($limit+1));
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);
    $items = array_map(static fn(array $row): array => mg_publishing_owner_project($pdo,$row), $rows);
    $next = null;
    if ($hasMore && $rows !== []) { $last=$rows[array_key_last($rows)]; $next=mg_publishing_cursor_encode(['kind'=>'owner:'.($status?:'all'),'time'=>(string)$last['updated_at'],'id'=>(string)$last['public_id']]); }
    return ['items'=>$items,'next_cursor'=>$next,'has_more'=>$hasMore,'limit'=>$limit,'status'=>$status?:'all'];
}

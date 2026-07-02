<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';
require_once dirname(__DIR__) . '/ads/_ads.php';

function mg_story_promote_trim(string $value, int $max, string $fallback = ''): string
{
    $value = mg_stories_text($value, $max, $fallback);
    return $value !== '' ? $value : $fallback;
}

function mg_story_promote_story(PDO $pdo, int $merchantId, string $storyPublicId): array
{
    mg_stories_require_schema($pdo);
    $stmt = $pdo->prepare("SELECT * FROM microgifter_stories WHERE public_id=? AND owner_user_id=? AND status='active' LIMIT 1");
    $stmt->execute([$storyPublicId, $merchantId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($story)) {
        throw new RuntimeException('Story is not available to promote.');
    }
    if ((int)($story['merchant_user_id'] ?? 0) !== $merchantId) {
        throw new RuntimeException('Only merchant stories can be promoted.');
    }
    return $story;
}

function mg_story_promote_linked_payload(PDO $pdo, array $story): array
{
    $linkedType = (string)($story['linked_type'] ?? 'none');
    if ($linkedType === 'product' && (int)($story['linked_product_id'] ?? 0) > 0 && mg_stories_table_exists($pdo, 'catalog_products') && mg_stories_table_exists($pdo, 'catalog_product_versions')) {
        $stmt = $pdo->prepare('SELECT p.id,p.public_id,p.slug,v.title,v.description FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.id=? LIMIT 1');
        $stmt->execute([(int)$story['linked_product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($product)) {
            return [
                'type' => 'product',
                'id' => (int)$product['id'],
                'public_id' => (string)($product['public_id'] ?? ''),
                'title' => mg_story_promote_trim((string)($product['title'] ?? ''), 190, 'Featured Product'),
                'description' => mg_stories_text((string)($product['description'] ?? ''), 360, ''),
                'url' => mg_stories_product_url($product),
                'cta' => 'View Product',
            ];
        }
    }
    if ($linkedType === 'campaign' && (int)($story['linked_campaign_id'] ?? 0) > 0 && mg_stories_table_exists($pdo, 'campaigns')) {
        $stmt = $pdo->prepare('SELECT id,public_id,public_slug,title,description FROM campaigns WHERE id=? LIMIT 1');
        $stmt->execute([(int)$story['linked_campaign_id']]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($campaign)) {
            return [
                'type' => 'campaign',
                'id' => (int)$campaign['id'],
                'public_id' => (string)($campaign['public_id'] ?? ''),
                'title' => mg_story_promote_trim((string)($campaign['title'] ?? ''), 190, 'Featured Campaign'),
                'description' => mg_stories_text((string)($campaign['description'] ?? ''), 360, ''),
                'url' => mg_stories_campaign_url($campaign),
                'cta' => 'View Campaign',
            ];
        }
    }
    return [
        'type' => 'story',
        'id' => null,
        'public_id' => (string)($story['public_id'] ?? ''),
        'title' => 'Promoted Story',
        'description' => '',
        'url' => mg_stories_safe_url($story['cta_url'] ?? '', true) ?? '/feed.php',
        'cta' => 'View Story',
    ];
}

function mg_story_promote_existing_campaign(PDO $pdo, int $merchantId, string $storyPublicId): ?array
{
    $needle = '%"source_story_id":"' . str_replace(['%', '_'], ['\\%', '\\_'], $storyPublicId) . '"%';
    $stmt = $pdo->prepare("SELECT c.public_id FROM ad_campaigns c INNER JOIN ad_creatives cr ON cr.ad_campaign_id=c.id WHERE c.merchant_id=? AND c.status<>'archived' AND cr.metadata_json LIKE ? ESCAPE '\\' ORDER BY c.updated_at DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantId, $needle]);
    $publicId = (string)($stmt->fetchColumn() ?: '');
    return $publicId !== '' ? mg_ads_load_campaign($pdo, $publicId, $merchantId, false) : null;
}

function mg_story_promote_campaign_input(PDO $pdo, int $merchantId, array $story): array
{
    $storyId = (string)$story['public_id'];
    $caption = mg_stories_text((string)($story['caption'] ?? ''), 280, '');
    $linked = mg_story_promote_linked_payload($pdo, $story);
    $fallbackTitle = $linked['title'] !== '' ? $linked['title'] : 'Promoted Story';
    $headline = mg_story_promote_trim($caption, 130, $fallbackTitle);
    $description = $caption !== '' ? $caption : ($linked['description'] !== '' ? $linked['description'] : 'Promoted from a Microgifter story.');
    $mediaUrl = mg_stories_safe_url($story['media_url'] ?? '', true);
    $thumbnailUrl = mg_stories_safe_url($story['thumbnail_url'] ?? '', true);
    $mediaType = (string)($story['media_type'] ?? 'image');
    $imageUrl = $mediaType === 'image' ? $mediaUrl : ($thumbnailUrl ?: null);
    $destinationUrl = mg_stories_safe_url($story['cta_url'] ?? '', true) ?? mg_stories_safe_url($linked['url'] ?? '', true) ?? '/feed.php';
    $ctaLabel = mg_story_promote_trim((string)($story['cta_label'] ?? ''), 80, (string)$linked['cta']);

    return [
        'title' => mg_story_promote_trim('Story boost: ' . $headline, 190, 'Story boost'),
        'headline' => $headline,
        'description' => mg_stories_text($description, 900, 'Promoted from a Microgifter story.'),
        'objective' => $linked['type'] === 'story' ? 'local_awareness' : 'claim_growth',
        'budget_type' => 'none',
        'image_url' => $imageUrl,
        'cta_label' => $ctaLabel,
        'destination_type' => $linked['type'],
        'destination_id' => is_int($linked['id']) ? $linked['id'] : null,
        'destination_url' => $destinationUrl,
        'sponsored_label' => 'Sponsored Story',
        'placements' => ['feed_sponsored_card','sidebar_sponsored_card'],
        'targeting' => [
            'source' => 'story_promote',
            'source_story_id' => $storyId,
            'source_story_type' => (string)($story['story_type'] ?? 'merchant'),
            'source_media_type' => $mediaType,
            'linked_type' => $linked['type'],
        ],
        'creative_metadata' => [
            'source_story_id' => $storyId,
            'source_story_media_type' => $mediaType,
            'source_story_media_url' => $mediaUrl,
            'source_story_thumbnail_url' => $thumbnailUrl,
            'source_story_caption' => $caption,
            'source_story_cta_label' => $ctaLabel,
            'source_story_cta_url' => $destinationUrl,
            'source_system' => 'feed_stories',
        ],
    ];
}

mg_require_method('POST');
$input = mg_input();
$user = mg_require_api_user();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$merchantId = (int)$user['id'];

try {
    if (function_exists('mg_rate_limit')) {
        mg_rate_limit('stories.promote', 'user:' . $merchantId, 20, 60);
    }
    mg_stories_require_schema($pdo);
    mg_ads_require_schema($pdo);
    mg_ads_require_merchant_user($user, $pdo);
    if (!mg_stories_user_can_merchant($user, $pdo)) {
        throw new RuntimeException('Merchant access is required to promote stories.');
    }

    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
    $story = mg_story_promote_story($pdo, $merchantId, $storyPublicId);
    $existing = mg_story_promote_existing_campaign($pdo, $merchantId, $storyPublicId);
    if (is_array($existing) && !empty($existing['public_id'])) {
        mg_ok([
            'schema_ready' => true,
            'campaign' => $existing,
            'created' => false,
            'redirect_url' => '/merchant-ad-manager.php?ad_campaign_id=' . rawurlencode((string)$existing['public_id']) . '&source=story',
        ], 'Story campaign draft already exists.');
        return;
    }

    $campaign = mg_ads_upsert_campaign($pdo, $merchantId, mg_story_promote_campaign_input($pdo, $merchantId, $story), null);
    mg_audit('stories.promoted_to_campaign', 'microgifter_story', ['story_id' => $storyPublicId, 'ad_campaign_id' => (string)($campaign['public_id'] ?? '')], $merchantId);
    if (function_exists('mg_event')) {
        mg_event('stories.promoted_to_campaign', ['story_id' => $storyPublicId, 'ad_campaign_id' => (string)($campaign['public_id'] ?? '')], $merchantId);
    }

    mg_ok([
        'schema_ready' => true,
        'campaign' => $campaign,
        'created' => true,
        'redirect_url' => '/merchant-ad-manager.php?ad_campaign_id=' . rawurlencode((string)($campaign['public_id'] ?? '')) . '&source=story',
    ], 'Campaign draft created from story.', 201);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.promote_failed', 'Story to campaign promotion failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $merchantId);
    mg_fail('Unable to promote story.', 500);
}

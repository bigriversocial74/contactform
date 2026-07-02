<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';
require_once dirname(__DIR__) . '/ads/_ads.php';

function mg_story_ad_text(string $value, int $max, string $fallback = ''): string
{
    $text = mg_stories_text($value, $max, $fallback);
    return $text !== '' ? $text : $fallback;
}

function mg_story_ad_load(PDO $pdo, int $merchantId, string $storyPublicId): array
{
    $stmt = $pdo->prepare("SELECT * FROM microgifter_stories WHERE public_id=? AND owner_user_id=? AND status='active' AND expires_at>NOW() LIMIT 1");
    $stmt->execute([$storyPublicId, $merchantId]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($story)) throw new RuntimeException('Story is not available to promote.');
    if ((int)($story['merchant_user_id'] ?? 0) !== $merchantId) throw new RuntimeException('Only merchant stories can be promoted.');
    return $story;
}

function mg_story_ad_link(PDO $pdo, array $story): array
{
    $type = (string)($story['linked_type'] ?? 'none');
    if ($type === 'product' && (int)($story['linked_product_id'] ?? 0) > 0 && mg_stories_table_exists($pdo, 'catalog_products') && mg_stories_table_exists($pdo, 'catalog_product_versions')) {
        $stmt = $pdo->prepare('SELECT p.id,p.public_id,p.slug,v.title,v.description FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.id=? LIMIT 1');
        $stmt->execute([(int)$story['linked_product_id']]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($product)) return ['type'=>'product','id'=>(int)$product['id'],'title'=>mg_story_ad_text((string)($product['title'] ?? ''),190,'Featured Product'),'description'=>mg_stories_text((string)($product['description'] ?? ''),480,''),'url'=>mg_stories_product_url($product),'cta'=>'View Product'];
    }
    if ($type === 'campaign' && (int)($story['linked_campaign_id'] ?? 0) > 0 && mg_stories_table_exists($pdo, 'campaigns')) {
        $stmt = $pdo->prepare('SELECT id,public_id,public_slug,title,description FROM campaigns WHERE id=? LIMIT 1');
        $stmt->execute([(int)$story['linked_campaign_id']]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($campaign)) return ['type'=>'campaign','id'=>(int)$campaign['id'],'title'=>mg_story_ad_text((string)($campaign['title'] ?? ''),190,'Featured Campaign'),'description'=>mg_stories_text((string)($campaign['description'] ?? ''),480,''),'url'=>mg_stories_campaign_url($campaign),'cta'=>'View Campaign'];
    }
    return ['type'=>'story','id'=>null,'title'=>'Promoted Story','description'=>'','url'=>mg_stories_safe_url($story['cta_url'] ?? '', true) ?? '/feed.php','cta'=>'View Story'];
}

function mg_story_ad_existing(PDO $pdo, int $merchantId, string $storyPublicId): ?array
{
    $encoded = mg_ads_json($storyPublicId);
    $stmt = $pdo->prepare("SELECT c.public_id FROM ad_campaigns c INNER JOIN ad_targeting_rules tr ON tr.ad_campaign_id=c.id WHERE c.merchant_id=? AND c.status<>'archived' AND tr.rule_type='source_story_id' AND tr.rule_value_json=? ORDER BY c.updated_at DESC,c.id DESC LIMIT 1");
    $stmt->execute([$merchantId, $encoded]);
    $publicId = (string)($stmt->fetchColumn() ?: '');
    return $publicId !== '' ? mg_ads_load_campaign($pdo, $publicId, $merchantId, false) : null;
}

function mg_story_ad_input(PDO $pdo, array $story): array
{
    $caption = mg_stories_text((string)($story['caption'] ?? ''), 280, '');
    $link = mg_story_ad_link($pdo, $story);
    $headline = mg_story_ad_text($caption, 130, (string)$link['title']);
    $description = $caption !== '' ? $caption : ((string)$link['description'] !== '' ? (string)$link['description'] : 'Promoted from a Microgifter story.');
    $mediaType = (string)($story['media_type'] ?? 'image');
    $mediaUrl = mg_stories_safe_url($story['media_url'] ?? '', true);
    $thumbUrl = mg_stories_safe_url($story['thumbnail_url'] ?? '', true);
    $destinationUrl = mg_stories_safe_url($story['cta_url'] ?? '', true) ?? mg_stories_safe_url($link['url'] ?? '', true) ?? '/feed.php';
    return [
        'title' => mg_story_ad_text('Story boost: ' . $headline, 190, 'Story boost'),
        'headline' => $headline,
        'description' => mg_stories_text($description, 900, 'Promoted from a Microgifter story.'),
        'objective' => $link['type'] === 'story' ? 'local_awareness' : 'claim_growth',
        'budget_type' => 'none',
        'image_url' => $mediaType === 'image' ? $mediaUrl : ($thumbUrl ?: null),
        'cta_label' => mg_story_ad_text((string)($story['cta_label'] ?? ''), 80, (string)$link['cta']),
        'destination_type' => (string)$link['type'],
        'destination_id' => is_int($link['id']) ? $link['id'] : null,
        'destination_url' => $destinationUrl,
        'sponsored_label' => 'Sponsored Story',
        'placements' => ['feed_sponsored_card','sidebar_sponsored_card'],
        'targeting' => [
            'source' => 'story_promote',
            'source_story_id' => (string)$story['public_id'],
            'source_story_type' => (string)($story['story_type'] ?? 'merchant'),
            'source_media_type' => $mediaType,
            'source_media_url' => $mediaUrl,
            'linked_type' => (string)$link['type'],
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
    if (function_exists('mg_rate_limit')) mg_rate_limit('stories.promote', 'user:' . $merchantId, 20, 60);
    mg_stories_require_schema($pdo);
    mg_ads_require_schema($pdo);
    mg_ads_require_merchant_user($user, $pdo);
    if (!mg_stories_user_can_merchant($user, $pdo)) throw new RuntimeException('Merchant access is required to promote stories.');

    $storyPublicId = mg_stories_public_id($input['story_id'] ?? '');
    $story = mg_story_ad_load($pdo, $merchantId, $storyPublicId);
    $existing = mg_story_ad_existing($pdo, $merchantId, $storyPublicId);
    if (is_array($existing) && !empty($existing['public_id'])) {
        mg_ok(['schema_ready'=>true,'campaign'=>$existing,'created'=>false,'redirect_url'=>'/merchant-ad-manager.php?ad_campaign_id=' . rawurlencode((string)$existing['public_id']) . '&source=story'], 'Story campaign draft already exists.');
        return;
    }

    $campaign = mg_ads_upsert_campaign($pdo, $merchantId, mg_story_ad_input($pdo, $story), null);
    $campaignId = (string)($campaign['public_id'] ?? '');
    mg_audit('stories.promoted_to_campaign', 'microgifter_story', ['story_id'=>$storyPublicId,'ad_campaign_id'=>$campaignId], $merchantId);
    if (function_exists('mg_event')) mg_event('stories.promoted_to_campaign', ['story_id'=>$storyPublicId,'ad_campaign_id'=>$campaignId], $merchantId);
    mg_ok(['schema_ready'=>true,'campaign'=>$campaign,'created'=>true,'redirect_url'=>'/merchant-ad-manager.php?ad_campaign_id=' . rawurlencode($campaignId) . '&source=story'], 'Campaign draft created from story.', 201);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.promote_failed', 'Story to campaign promotion failed.', ['exception_class'=>$error::class,'message'=>$error->getMessage()], $merchantId);
    mg_fail('Unable to promote story.', 500);
}

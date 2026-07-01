<?php
declare(strict_types=1);

require_once __DIR__ . '/_stories.php';

mg_require_method('POST');
$input = mg_input();
$user = mg_require_api_user();
mg_require_csrf_for_write($input);
$pdo = mg_db();
$userId = (int)$user['id'];
mg_rate_limit('stories.create', 'user:' . $userId, 30, 60);

try {
    mg_stories_require_schema($pdo);
    $assetPublicId = mg_stories_public_id($input['media_asset_id'] ?? '');
    $asset = mg_stories_asset_for_owner($pdo, $userId, $assetPublicId);
    $mediaType = (string)$asset['asset_type'];
    $mediaUrl = mg_storage_asset_public_url((string)$asset['public_id']);
    $caption = mg_stories_text($input['caption'] ?? '', 280, '');
    $linkedType = strtolower(trim((string)($input['linked_type'] ?? 'none')));
    if (!in_array($linkedType, ['none','product','campaign'], true)) $linkedType = 'none';

    $isMerchant = mg_stories_user_can_merchant($user, $pdo);
    $merchantId = $isMerchant ? $userId : null;
    $linkedProductId = null;
    $linkedCampaignId = null;
    $storyType = $isMerchant ? 'merchant' : 'user';
    $ctaLabel = mg_stories_text($input['cta_label'] ?? '', 80, '');
    $ctaUrl = mg_stories_safe_url($input['cta_url'] ?? '', true);

    if ($linkedType !== 'none') {
        if (!$isMerchant) throw new RuntimeException('Merchant access is required to attach products or campaigns.');
        $linkedPublicId = mg_stories_public_id($input['linked_id'] ?? '');
        if ($linkedType === 'product') {
            $product = mg_stories_product_for_merchant($pdo, $userId, $linkedPublicId);
            if (!is_array($product)) throw new RuntimeException('Product is not available to this merchant.');
            $linkedProductId = (int)$product['id'];
            $storyType = 'product';
            $ctaLabel = $ctaLabel !== '' ? $ctaLabel : 'View Product';
            $ctaUrl = mg_stories_product_url($product);
        } elseif ($linkedType === 'campaign') {
            $campaign = mg_stories_campaign_for_merchant($pdo, $userId, $linkedPublicId);
            if (!is_array($campaign)) throw new RuntimeException('Campaign is not available to this merchant.');
            $linkedCampaignId = (int)$campaign['id'];
            $storyType = 'campaign';
            $ctaLabel = $ctaLabel !== '' ? $ctaLabel : 'View Campaign';
            $ctaUrl = mg_stories_campaign_url($campaign);
        }
    } else {
        $ctaLabel = $ctaLabel !== '' ? $ctaLabel : null;
        $ctaUrl = $ctaUrl ?: null;
    }

    $publicId = mg_stories_uuid();
    $stmt = $pdo->prepare("INSERT INTO microgifter_stories (public_id,owner_user_id,merchant_user_id,linked_type,linked_product_id,linked_campaign_id,story_type,media_asset_id,media_type,media_url,thumbnail_url,caption,cta_label,cta_url,status,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW(),DATE_ADD(NOW(), INTERVAL 24 HOUR))");
    $stmt->execute([$publicId,$userId,$merchantId,$linkedType,$linkedProductId,$linkedCampaignId,$storyType,(int)$asset['id'],$mediaType,$mediaUrl,$mediaType === 'image' ? $mediaUrl : null,$caption !== '' ? $caption : null,$ctaLabel,$ctaUrl]);

    mg_audit('stories.created', 'microgifter_story', ['story_id' => $publicId, 'linked_type' => $linkedType, 'media_type' => $mediaType], $userId);
    mg_ok(['story_id' => $publicId, 'expires_in_hours' => 24], 'Story published.', 201);
} catch (InvalidArgumentException $error) {
    mg_fail($error->getMessage(), 422);
} catch (RuntimeException $error) {
    mg_fail($error->getMessage(), 422);
} catch (Throwable $error) {
    mg_security_log('error', 'stories.create_failed', 'Feed Story creation failed.', ['exception_class' => $error::class, 'message' => $error->getMessage()], $userId);
    mg_fail('Unable to publish story.', 500);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/_merchant.php';
require_once dirname(__DIR__, 2) . '/includes/storage.php';
require_once dirname(__DIR__, 2) . '/includes/campaign-types.php';

function mg_lqc_base_url(): string
{
    $base = rtrim((string)(defined('MG_APP_URL') ? MG_APP_URL : ''), '/');
    if ($base !== '') return $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    return $scheme . '://' . $host;
}

function mg_lqc_rules(mixed $json): array
{
    $decoded = json_decode((string)($json ?? ''), true);
    return is_array($decoded) ? $decoded : [];
}

function mg_lqc_campaign(PDO $pdo, int $merchantId, string $publicId, bool $lock = false): array
{
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid Loyalty Quest.', 422);
    $sql = "SELECT c.*,rt.title reward_title,rt.description reward_description,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency,
        COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name
        FROM campaigns c
        INNER JOIN users u ON u.id=c.merchant_user_id
        LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
        LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
        LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id
        WHERE c.public_id=? AND c.merchant_user_id=? AND c.campaign_type='loyalty_quest' LIMIT 1" . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicId,$merchantId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) mg_fail('Loyalty Quest not found.', 404);
    $campaign['rules'] = mg_lqc_rules($campaign['rules_json'] ?? null);
    return $campaign;
}

function mg_lqc_public_url(array $campaign): string
{
    $ref = (string)($campaign['public_slug'] ?: $campaign['public_id']);
    return mg_lqc_base_url() . '/loyalty-quest.php?campaign=' . rawurlencode($ref);
}

function mg_lqc_public_cover_url(array $campaign, string $assetId): string
{
    $assetId = strtolower(trim($assetId));
    if ($assetId === '') return '';
    $ref = (string)($campaign['public_slug'] ?: $campaign['public_id']);
    return mg_lqc_base_url() . '/api/public/loyalty-quest/media.php?campaign=' . rawurlencode($ref) . '&asset=' . rawurlencode($assetId);
}

function mg_lqc_https_url(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';
    if (mb_strlen($url) > 700 || filter_var($url, FILTER_VALIDATE_URL) === false) mg_fail('Enter a valid image URL.', 422);
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) mg_fail('External images must use HTTPS.', 422);
    if (!empty($parts['user']) || !empty($parts['pass'])) mg_fail('Image URLs cannot contain credentials.', 422);
    return $url;
}

function mg_lqc_asset(PDO $pdo, int $merchantId, string $publicId): ?array
{
    $publicId = strtolower(trim($publicId));
    if ($publicId === '') return null;
    if (strlen($publicId) !== 36 || preg_match('/^[a-f0-9-]{36}$/', $publicId) !== 1) mg_fail('Invalid media asset.', 422);
    $stmt = $pdo->prepare("SELECT public_id,original_filename,mime_type,width_px,height_px,metadata_json FROM catalog_assets WHERE public_id=? AND owner_user_id=? AND asset_type='image' AND status='ready' LIMIT 1");
    $stmt->execute([$publicId,$merchantId]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) mg_fail('Image asset not found in this merchant workspace.', 404);
    $asset['url'] = mg_storage_asset_public_url($publicId);
    return $asset;
}

function mg_lqc_value(array $campaign): string
{
    if (($campaign['value_type'] ?? '') === 'percent' && $campaign['value_percent'] !== null) {
        return rtrim(rtrim(number_format((float)$campaign['value_percent'], 2), '0'), '.') . '%';
    }
    if ($campaign['value_amount_cents'] !== null) {
        return strtoupper((string)($campaign['currency'] ?? 'USD')) . ' ' . number_format(((int)$campaign['value_amount_cents']) / 100, 2);
    }
    return '';
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$user = mg_merchant_require_permission($method === 'GET' ? 'merchant.campaigns.view' : 'merchant.campaigns.manage');
$merchantId = (int)$user['id'];
$pdo = mg_db();
mg_merchant_ensure_workspace($pdo,$user);

if ($method === 'GET') {
    $campaignId = strtolower(trim((string)($_GET['campaign_id'] ?? '')));
    $campaignsStmt = $pdo->prepare("SELECT public_id,title,status,public_slug,updated_at FROM campaigns WHERE merchant_user_id=? AND campaign_type='loyalty_quest' ORDER BY updated_at DESC,id DESC LIMIT 150");
    $campaignsStmt->execute([$merchantId]);
    $campaigns = $campaignsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $assetsStmt = $pdo->prepare("SELECT public_id,original_filename,mime_type,width_px,height_px,created_at FROM catalog_assets WHERE owner_user_id=? AND asset_type='image' AND status='ready' ORDER BY created_at DESC,id DESC LIMIT 100");
    $assetsStmt->execute([$merchantId]);
    $assets = [];
    foreach ($assetsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $asset) {
        $asset['url'] = mg_storage_asset_public_url((string)$asset['public_id']);
        $assets[] = $asset;
    }

    $payload = ['campaigns'=>$campaigns,'assets'=>$assets,'campaign'=>null,'schema_ready'=>true];
    if ($campaignId !== '') {
        $campaign = mg_lqc_campaign($pdo,$merchantId,$campaignId,false);
        $rules = $campaign['rules'];
        $creative = is_array($rules['creative'] ?? null) ? $rules['creative'] : [];
        $coverAssetId = (string)($creative['cover_asset_id'] ?? $rules['cover_image_asset_id'] ?? '');
        $coverUrl = (string)($creative['cover_url'] ?? $rules['cover_image_url'] ?? '');
        $asset = $coverAssetId !== '' ? mg_lqc_asset($pdo,$merchantId,$coverAssetId) : null;
        if ($asset) $coverUrl = mg_lqc_public_cover_url($campaign,(string)$asset['public_id']);
        $publicUrl = mg_lqc_public_url($campaign);
        $embedId = 'microgifter-loyalty-quest-' . str_replace('-','',(string)$campaign['public_id']);
        $embedCode = '<div id="' . $embedId . '" data-microgifter-loyalty-quest="' . htmlspecialchars((string)($campaign['public_slug'] ?: $campaign['public_id']),ENT_QUOTES,'UTF-8') . '"></div>' . "\n" .
            '<script async src="' . mg_lqc_base_url() . '/assets/js/loyalty-quest-embed.js" data-target="' . $embedId . '" data-campaign="' . htmlspecialchars((string)($campaign['public_slug'] ?: $campaign['public_id']),ENT_QUOTES,'UTF-8') . '" data-api-base="' . mg_lqc_base_url() . '"></script>';
        $payload['campaign'] = [
            'id'=>(string)$campaign['public_id'],'title'=>(string)$campaign['title'],'description'=>(string)($campaign['description'] ?? ''),'status'=>(string)$campaign['status'],
            'merchant_name'=>(string)$campaign['merchant_name'],'reward_title'=>(string)($campaign['reward_title'] ?? 'Microgifter reward'),'reward_description'=>(string)($campaign['reward_description'] ?? ''),'reward_value'=>mg_lqc_value($campaign),
            'public_url'=>$publicUrl,'qr_url'=>'/api/merchant/loyalty-quest-qr.php?campaign_id=' . rawurlencode((string)$campaign['public_id']),
            'creative'=>[
                'cover_asset_id'=>$coverAssetId,'cover_url'=>$coverUrl,'image_alt'=>(string)($creative['image_alt'] ?? $rules['cover_image_alt'] ?? ''),
                'headline'=>(string)($creative['headline'] ?? $campaign['title']),'cta'=>(string)($creative['cta'] ?? 'Start Loyalty Quest'),
                'terms'=>(string)($creative['terms'] ?? 'Terms and availability apply.'),'accent'=>(string)($creative['accent'] ?? '#111827'),
                'embed_code'=>$embedCode,
            ],
        ];
    }
    mg_ok($payload);
}

if ($method !== 'POST') mg_fail('Method not allowed.',405);
$input = mg_input();
mg_require_csrf_for_write($input);
$campaignId = strtolower(trim((string)($input['campaign_id'] ?? '')));
$coverAssetId = strtolower(trim((string)($input['cover_asset_id'] ?? '')));
$externalUrl = mg_lqc_https_url((string)($input['cover_url'] ?? ''));
$imageAlt = trim((string)($input['image_alt'] ?? ''));
$headline = trim((string)($input['headline'] ?? ''));
$cta = trim((string)($input['cta'] ?? ''));
$terms = trim((string)($input['terms'] ?? ''));
$accent = strtolower(trim((string)($input['accent'] ?? '#111827')));
if (mb_strlen($imageAlt)>240 || mb_strlen($headline)>180 || mb_strlen($cta)>80 || mb_strlen($terms)>500) mg_fail('Creative copy is too long.',422);
if (preg_match('/^#[0-9a-f]{6}$/',$accent)!==1) $accent='#111827';
if ($coverAssetId !== '' && $externalUrl !== '') mg_fail('Choose either a library image or an external image URL.',422);

$pdo->beginTransaction();
try {
    $campaign = mg_lqc_campaign($pdo,$merchantId,$campaignId,true);
    $asset = $coverAssetId !== '' ? mg_lqc_asset($pdo,$merchantId,$coverAssetId) : null;
    $coverUrl = $asset ? mg_lqc_public_cover_url($campaign,(string)$asset['public_id']) : $externalUrl;
    $rules = $campaign['rules'];
    $rules['cover_image_asset_id'] = $asset ? (string)$asset['public_id'] : '';
    $rules['cover_image_url'] = $coverUrl;
    $rules['cover_image_alt'] = $imageAlt;
    $rules['creative'] = [
        'version'=>1,'cover_asset_id'=>$asset ? (string)$asset['public_id'] : '','cover_url'=>$coverUrl,'image_alt'=>$imageAlt,
        'headline'=>$headline !== '' ? $headline : (string)$campaign['title'],'cta'=>$cta !== '' ? $cta : 'Start Loyalty Quest',
        'terms'=>$terms !== '' ? $terms : 'Terms and availability apply.','accent'=>$accent,'updated_at'=>gmdate('c'),
    ];
    $encoded = json_encode($rules,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $pdo->prepare('UPDATE campaigns SET rules_json=?,updated_at=NOW() WHERE id=? AND merchant_user_id=? AND campaign_type=\'loyalty_quest\'')
        ->execute([$encoded,(int)$campaign['id'],$merchantId]);
    mg_audit('merchant.loyalty_quest_creative_saved','campaign',['campaign_id'=>$campaignId,'cover_asset_id'=>$asset['public_id']??null,'has_external_cover'=>$externalUrl!=='','creative_version'=>1],$merchantId);
    $pdo->commit();
    mg_ok(['campaign_id'=>$campaignId,'cover_url'=>$coverUrl,'creative'=>$rules['creative'],'public_url'=>mg_lqc_public_url($campaign)],'Loyalty Quest creative saved.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    mg_security_log('error','merchant.loyalty_quest_creative_save_failed','Unable to save Loyalty Quest creative.',['exception_class'=>$error::class],$merchantId);
    mg_fail('Unable to save Loyalty Quest creative.',500);
}

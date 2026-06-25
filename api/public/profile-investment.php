<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/profiles/_public_profile.php';
mg_require_method('GET');

function mg_pi_table(PDO $pdo, string $table): bool
{
    static $cache = [];
    $allowed = [
        'public_profiles','catalog_products','catalog_product_versions','campaigns','campaign_contacts','campaign_events','wallet_items','feed_posts',
        'social_follows','social_mutation_requests','reward_templates',
        'distribution_programs','distribution_source_connections','distribution_source_events','distribution_allocations','distribution_daily_metrics',
        'account_stamp_balances','stamp_ledger_entries'
    ];
    if (!in_array($table, $allowed, true)) return false;
    if (array_key_exists($table, $cache)) return $cache[$table];
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable) {
        try { $stmt = $pdo->prepare('SHOW TABLES LIKE ?'); $stmt->execute([$table]); return $cache[$table] = (bool)$stmt->fetchColumn(); }
        catch (Throwable) { return $cache[$table] = false; }
    }
}
function mg_pi_row(PDO $pdo, string $sql, array $params = []): array { try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetch(PDO::FETCH_ASSOC) ?: []; } catch (Throwable) { return []; } }
function mg_pi_rows(PDO $pdo, string $sql, array $params = []): array { try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (Throwable) { return []; } }
function mg_pi_money(int $cents, string $currency = 'USD'): string { $prefix = strtoupper($currency) === 'USD' ? '$' : strtoupper($currency) . ' '; return $prefix . number_format(max(0, $cents) / 100, $cents > 0 && $cents < 10000 ? 2 : 0); }
function mg_pi_num(int|float $value): string { return number_format((float)$value, is_float($value) && fmod($value, 1.0) !== 0.0 ? 1 : 0); }
function mg_pi_pct(?float $value, int $places = 0): string { return $value === null || !is_finite($value) ? 'No trend' : number_format($value, $places) . '%'; }
function mg_pi_metric(string $display, int|float|string|null $raw = null, bool $hasData = true, ?string $detail = null): array { return ['display'=>$display,'raw'=>$raw,'has_data'=>$hasData,'detail'=>$detail]; }
function mg_pi_rating(int $score): string { return $score >= 85 ? 'Premium Demand' : ($score >= 70 ? 'High Demand' : ($score >= 50 ? 'Building Demand' : ($score >= 25 ? 'Early Demand' : 'No Market Signal'))); }
function mg_pi_symbol(string $name, string $slug): string { $symbol = preg_replace('/[^A-Z0-9]/', '', strtoupper($slug)); if ($symbol === '') { $symbol = ''; foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) $symbol .= substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($part)), 0, 1); } return substr($symbol !== '' ? $symbol : 'MGFT', 0, 5); }
function mg_pi_campaign_url(array $row): ?string { $slug = trim((string)($row['public_slug'] ?? '')); if ($slug === '') return null; $page = match ((string)($row['campaign_type'] ?? '')) { 'newsletter_signup'=>'/newsletter-signup.php','contest_giveaway'=>'/contest.php','qr_reward_drop'=>'/qr-reward.php','referral_reward'=>'/referral-reward.php','birthday_vip'=>'/birthday-vip.php','agent_offer'=>'/agent-offer.php', default=>'/campaign.php' }; return $page . '?campaign=' . rawurlencode($slug); }
function mg_pi_campaign_progress(array $row): ?int { $limit = (int)($row['quantity_limit'] ?? 0); $issued = (int)($row['issued_count'] ?? 0); if ($limit > 0) return max(0, min(100, (int)round(($issued / $limit) * 100))); $starts = !empty($row['starts_at']) ? strtotime((string)$row['starts_at']) : false; $ends = !empty($row['ends_at']) ? strtotime((string)$row['ends_at']) : false; return $starts !== false && $ends !== false && $ends > $starts ? max(0, min(100, (int)round(((time() - $starts) / ($ends - $starts)) * 100))) : null; }

$pdo = mg_db();
$slug = mg_public_profile_slug((string)($_GET['slug'] ?? ''));
$currentUser = mg_current_user();
$viewerId = (int)($currentUser['id'] ?? 0);
$viewerId = $viewerId > 0 ? $viewerId : null;

try { $source = mg_public_profile_read($pdo, $slug, ['viewer_id'=>$viewerId, 'preview'=>!empty($_GET['preview']), 'product_limit'=>6, 'post_limit'=>6, 'plan_limit'=>6]); }
catch (Throwable) { mg_fail('Profile not found.', 404); }

$owner = mg_pi_row($pdo, 'SELECT id,user_id,website_url FROM public_profiles WHERE slug=? LIMIT 1', [$slug]);
$ownerId = (int)($owner['user_id'] ?? 0);
if ($ownerId < 1) mg_fail('Profile not found.', 404);

$profile = $source['profile'] ?? [];
$counts = $source['social_counts'] ?? [];
$display = (string)($profile['display_name'] ?? 'Microgifter Merchant');
$tagline = (string)($profile['headline'] ?? $profile['biography'] ?? 'Tokenize local experiences and create future demand.');
$followers = (int)($counts['followers'] ?? 0);
$supporters = (int)($counts['supporters'] ?? 0);
$productCount = (int)($counts['published_products'] ?? 0);

$productValue = 0; $floor = 0;
if (mg_pi_table($pdo, 'catalog_products') && mg_pi_table($pdo, 'catalog_product_versions')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) product_count,COALESCE(SUM(cpv.unit_value_cents),0) product_value,COALESCE(MIN(NULLIF(cpv.unit_value_cents,0)),0) floor FROM catalog_products cp INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published' WHERE cp.merchant_user_id=? AND cp.status='published'", [$ownerId]);
    $productCount = (int)($row['product_count'] ?? $productCount); $productValue = (int)($row['product_value'] ?? 0); $floor = (int)($row['floor'] ?? 0);
}

$totalCampaigns = 0; $activeCampaigns = 0; $campaignIssued = 0; $campaignCapacity = 0; $campaignItems = []; $contacts = 0; $events = 0; $agentDiscoverable = 0;
if (mg_pi_table($pdo, 'campaigns')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) total_campaigns,SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) active_campaigns,COALESCE(SUM(issued_count),0) issued,COALESCE(SUM(quantity_limit),0) capacity,COALESCE(SUM(CASE WHEN agent_discoverable=1 AND status='active' THEN 1 ELSE 0 END),0) agent_items FROM campaigns WHERE merchant_user_id=? AND status NOT IN ('archived')", [$ownerId]);
    $totalCampaigns = (int)($row['total_campaigns'] ?? 0); $activeCampaigns = (int)($row['active_campaigns'] ?? 0); $campaignIssued = (int)($row['issued'] ?? 0); $campaignCapacity = (int)($row['capacity'] ?? 0); $agentDiscoverable += (int)($row['agent_items'] ?? 0);
    foreach (mg_pi_rows($pdo, "SELECT public_id,campaign_type,title,description,status,starts_at,ends_at,quantity_limit,issued_count,public_slug,updated_at FROM campaigns WHERE merchant_user_id=? AND status IN ('active','paused') ORDER BY CASE WHEN status='active' THEN 0 ELSE 1 END,COALESCE(starts_at,updated_at) DESC,public_id DESC LIMIT 8", [$ownerId]) as $row) $campaignItems[] = ['id'=>(string)$row['public_id'],'type'=>(string)$row['campaign_type'],'title'=>(string)$row['title'],'description'=>$row['description'] !== null ? (string)$row['description'] : null,'status'=>(string)$row['status'],'progress'=>mg_pi_campaign_progress($row),'issued_count'=>(int)($row['issued_count'] ?? 0),'quantity_limit'=>$row['quantity_limit'] !== null ? (int)$row['quantity_limit'] : null,'url'=>mg_pi_campaign_url($row)];
}
if (mg_pi_table($pdo, 'reward_templates')) $agentDiscoverable += (int)(mg_pi_row($pdo, "SELECT COUNT(*) total FROM reward_templates WHERE merchant_user_id=? AND status='active' AND agent_discoverable=1", [$ownerId])['total'] ?? 0);
if (mg_pi_table($pdo, 'campaign_contacts')) $contacts = (int)(mg_pi_row($pdo, 'SELECT COUNT(*) total FROM campaign_contacts WHERE merchant_user_id=?', [$ownerId])['total'] ?? 0);
if (mg_pi_table($pdo, 'campaign_events')) $events = (int)(mg_pi_row($pdo, 'SELECT COUNT(*) total FROM campaign_events WHERE merchant_user_id=?', [$ownerId])['total'] ?? 0);

$distributionPrograms = 0; $activeDistributionPrograms = 0; $distributionChannels = 0; $distributionConnections = 0; $distributionEvents30 = 0; $distributionAllocations30 = 0; $distributionIssuedValue30 = 0;
if (mg_pi_table($pdo, 'distribution_programs')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) programs,SUM(CASE WHEN status IN ('active','scheduled') THEN 1 ELSE 0 END) active_programs FROM distribution_programs WHERE merchant_user_id=? AND status NOT IN ('archived','cancelled')", [$ownerId]);
    $distributionPrograms = (int)($row['programs'] ?? 0); $activeDistributionPrograms = (int)($row['active_programs'] ?? 0);
}
if (mg_pi_table($pdo, 'distribution_source_connections')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) connections,COUNT(DISTINCT source_type) channels FROM distribution_source_connections WHERE merchant_user_id=? AND status='active'", [$ownerId]);
    $distributionConnections = (int)($row['connections'] ?? 0); $distributionChannels = (int)($row['channels'] ?? 0);
}
if (mg_pi_table($pdo, 'distribution_source_events')) $distributionEvents30 = (int)(mg_pi_row($pdo, "SELECT COUNT(*) total FROM distribution_source_events WHERE merchant_user_id=? AND received_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND status IN ('validated','queued','processed','received')", [$ownerId])['total'] ?? 0);
if (mg_pi_table($pdo, 'distribution_allocations') && mg_pi_table($pdo, 'distribution_programs')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) allocations,COALESCE(SUM(da.unit_value_cents*da.quantity),0) issued_value FROM distribution_allocations da INNER JOIN distribution_programs dp ON dp.id=da.program_id WHERE dp.merchant_user_id=? AND da.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND da.status IN ('reserved','queued','issuing','issued')", [$ownerId]);
    $distributionAllocations30 = (int)($row['allocations'] ?? 0); $distributionIssuedValue30 = (int)($row['issued_value'] ?? 0);
}

$stampBalance = 0; $stampPurchased = 0; $stampUsed = 0; $stampIncluded = 0; $stampDebits30 = 0; $stampCredits30 = 0;
if (mg_pi_table($pdo, 'account_stamp_balances')) {
    $row = mg_pi_row($pdo, "SELECT COALESCE(SUM(balance),0) balance,COALESCE(SUM(purchased_stamps),0) purchased,COALESCE(SUM(used_stamps),0) used,COALESCE(SUM(included_monthly_stamps),0) included FROM account_stamp_balances WHERE account_user_id=?", [$ownerId]);
    $stampBalance = (int)($row['balance'] ?? 0); $stampPurchased = (int)($row['purchased'] ?? 0); $stampUsed = (int)($row['used'] ?? 0); $stampIncluded = (int)($row['included'] ?? 0);
}
if (mg_pi_table($pdo, 'stamp_ledger_entries')) {
    $row = mg_pi_row($pdo, "SELECT COALESCE(SUM(CASE WHEN entry_type='debit' THEN ABS(delta) ELSE 0 END),0) debits,COALESCE(SUM(CASE WHEN entry_type='credit' THEN delta ELSE 0 END),0) credits FROM stamp_ledger_entries WHERE account_user_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$ownerId]);
    $stampDebits30 = (int)($row['debits'] ?? 0); $stampCredits30 = (int)($row['credits'] ?? 0);
}

$issued30 = 0; $redeemed30 = 0; $volume30 = 0; $previousVolume = 0; $outstanding = 0; $activity = []; $series = [];
if (mg_pi_table($pdo, 'wallet_items')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) issued_30,SUM(CASE WHEN status IN ('redeemed','claimed') THEN 1 ELSE 0 END) redeemed_30,COALESCE(SUM(value_cents_snapshot),0) volume_30,COALESCE(SUM(CASE WHEN status NOT IN ('redeemed','expired','cancelled') THEN value_cents_snapshot ELSE 0 END),0) outstanding FROM wallet_items WHERE merchant_user_id=? AND issued_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$ownerId]);
    $issued30 = (int)($row['issued_30'] ?? 0); $redeemed30 = (int)($row['redeemed_30'] ?? 0); $volume30 = (int)($row['volume_30'] ?? 0); $outstanding = (int)($row['outstanding'] ?? 0);
    $previousVolume = (int)(mg_pi_row($pdo, "SELECT COALESCE(SUM(value_cents_snapshot),0) total FROM wallet_items WHERE merchant_user_id=? AND issued_at>=DATE_SUB(NOW(),INTERVAL 60 DAY) AND issued_at<DATE_SUB(NOW(),INTERVAL 30 DAY)", [$ownerId])['total'] ?? 0);
    foreach (mg_pi_rows($pdo, "SELECT title_snapshot,status,value_cents_snapshot,currency_snapshot,issued_at,claimed_at,redeemed_at,created_at FROM wallet_items WHERE merchant_user_id=? ORDER BY COALESCE(redeemed_at,claimed_at,issued_at,created_at) DESC LIMIT 5", [$ownerId]) as $row) $activity[] = ['title'=>(string)($row['title_snapshot'] ?? 'Reward'),'status'=>(string)($row['status'] ?? 'issued'),'value'=>mg_pi_money((int)($row['value_cents_snapshot'] ?? 0),(string)($row['currency_snapshot'] ?? 'USD')),'date'=>(string)($row['redeemed_at'] ?? $row['claimed_at'] ?? $row['issued_at'] ?? $row['created_at'] ?? '')];
    foreach (mg_pi_rows($pdo, "SELECT DATE(issued_at) day,COALESCE(SUM(value_cents_snapshot),0) value_cents FROM wallet_items WHERE merchant_user_id=? AND issued_at>=DATE_SUB(CURDATE(),INTERVAL 29 DAY) GROUP BY DATE(issued_at) ORDER BY day ASC", [$ownerId]) as $row) $series[] = ['date'=>(string)$row['day'],'value_cents'=>(int)$row['value_cents']];
}

$postCount = 0; $interactions = 0; $trending = [];
if (mg_pi_table($pdo, 'feed_posts')) {
    $row = mg_pi_row($pdo, "SELECT COUNT(*) post_count,COALESCE(SUM(comment_count+reaction_count+share_count+save_count),0) interactions FROM feed_posts WHERE merchant_user_id=? AND status='published' AND moderation_status NOT IN ('hidden','removed')", [$ownerId]);
    $postCount = (int)($row['post_count'] ?? 0); $interactions = (int)($row['interactions'] ?? 0);
    if (mg_pi_table($pdo, 'catalog_products') && mg_pi_table($pdo, 'catalog_product_versions')) foreach (mg_pi_rows($pdo, "SELECT cp.slug,cpv.title,COALESCE(SUM(fp.comment_count*3+fp.reaction_count+fp.share_count*4+fp.save_count*2),0) score FROM catalog_products cp INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published' INNER JOIN feed_posts fp ON fp.catalog_product_id=cp.id AND fp.status='published' AND fp.moderation_status NOT IN ('hidden','removed') WHERE cp.merchant_user_id=? AND cp.status='published' AND fp.created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY cp.id,cp.slug,cpv.title HAVING score>0 ORDER BY score DESC,cp.public_id DESC LIMIT 5", [$ownerId]) as $row) $trending[] = ['title'=>(string)$row['title'],'score'=>(int)$row['score'],'url'=>'/product.php?p='.rawurlencode((string)$row['slug'])];
}

$newFollowers30 = mg_pi_table($pdo, 'social_follows') ? (int)(mg_pi_row($pdo, "SELECT COUNT(*) total FROM social_follows WHERE followed_user_id=? AND status='active' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)", [$ownerId])['total'] ?? 0) : 0;
$lostFollowers30 = 0;
if (mg_pi_table($pdo, 'social_mutation_requests')) {
    $safeSlugNeedle = '%"profile_slug":"' . str_replace(['%', '_'], ['\%', '\_'], $slug) . '"%';
    $lostFollowers30 = (int)(mg_pi_row($pdo, "SELECT COUNT(*) total FROM social_mutation_requests WHERE action IN ('relationship.unfollow','relationship.block') AND completed_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) AND response_json LIKE ?", [$safeSlugNeedle])['total'] ?? 0);
}
$followerMomentum = $newFollowers30 - $lostFollowers30;

$redemptionRate = $issued30 > 0 ? ($redeemed30 / $issued30) * 100 : 0.0;
$engagementRate = $interactions > 0 ? ($interactions / max(1, $followers + $postCount)) * 100 : 0.0;
$marketGrowth = $previousVolume > 0 ? (($volume30 - $previousVolume) / $previousVolume) * 100 : null;
$demandValue = $volume30 + $outstanding;
$activeDrops = $productCount + $activeCampaigns;
$hasData = $productCount > 0 || $activeCampaigns > 0 || $issued30 > 0 || $interactions > 0 || $volume30 > 0 || $followers > 0 || $distributionPrograms > 0 || $stampBalance > 0;

$productDepth = min(14, $productCount * 3.5);
$campaignVelocity = min(16, ($activeCampaigns * 5) + min(5, $campaignIssued / 5) + min(4, $events / 25));
$redemptionQuality = $issued30 > 0 ? min(14, $redemptionRate * 0.14) : 0;
$engagementSignal = min(14, log10($interactions + $supporters + 1) * 6);
$commerceVolume = min(12, log10((($volume30 + $outstanding + $productValue) / 100) + 1) * 3.5);
$distributionReach = min(14, ($activeDistributionPrograms * 3) + ($distributionChannels * 2) + min(4, log10($distributionEvents30 + $distributionAllocations30 + 1) * 3) + min(4, $agentDiscoverable * 1.5));
$stampPower = min(8, log10($stampBalance + 1) * 2.5 + log10($stampDebits30 + 1) * 2.5 + log10($stampPurchased + 1) * 1.5);
$followerGrowth = min(10, log10($followers + 1) * 5);
$followerMomentumAdjustment = $followerMomentum >= 0 ? min(6, log10($followerMomentum + 1) * 4) : -min(10, abs($followerMomentum) * 2.5);
$merchantScore = max(0, min(100, (int)round($productDepth + $campaignVelocity + $redemptionQuality + $engagementSignal + $commerceVolume + $distributionReach + $stampPower + $followerGrowth + $followerMomentumAdjustment)));
$rating = mg_pi_rating($merchantScore);
$confidence = !$hasData ? 'no data' : ((($productCount > 0 ? 1 : 0) + ($activeCampaigns > 0 ? 1 : 0) + ($issued30 > 0 ? 1 : 0) + ($interactions > 0 ? 1 : 0) + ($followers > 0 ? 1 : 0) + ($distributionPrograms > 0 ? 1 : 0) + ($stampBalance > 0 ? 1 : 0)) >= 5 ? 'strong' : 'developing');

$followerAddValue = $hasData ? (50 + ($productCount * 15) + ($activeCampaigns * 35) + ($distributionChannels * 40) + ($agentDiscoverable * 30) + min(200, (int)round($redemptionRate * 2)) + min(300, (int)round($volume30 / 1000)) + min(200, $interactions * 5) + min(150, $stampDebits30 * 2)) : 0;
$followerBrandValue = $followers * $followerAddValue;
$followerMomentumValue = ($newFollowers30 * $followerAddValue) - ($lostFollowers30 * $followerAddValue * 2);
$distributionValue = ($activeDistributionPrograms * 5000) + ($distributionChannels * 3000) + ($distributionEvents30 * 50) + ($distributionAllocations30 * 150) + $distributionIssuedValue30 + ($agentDiscoverable * 2000);
$stampInventoryValue = $stampBalance * 2;
$stampSpendValue = $stampDebits30 * 5;
$tickerValue = max(0, $demandValue + $productValue + ($activeCampaigns * 2500) + ($campaignIssued * 150) + ($interactions * 100) + $followerBrandValue + $followerMomentumValue + ($supporters * 250) + $distributionValue + $stampInventoryValue + $stampSpendValue);
$tickerSymbol = mg_pi_symbol($display, $slug);

$analytics = [
    ['label'=>'Merchant Score','value'=>(string)$merchantScore,'detail'=>'Composite score across products, campaigns, redemption, engagement, commerce, distribution, stamps, and follower momentum.','has_data'=>$hasData],
    ['label'=>'Ticker Value','value'=>mg_pi_money($tickerValue),'detail'=>'Merchant value proxy including demand, products, campaigns, distribution, stamps, engagement, followers, and supporters.','has_data'=>$tickerValue > 0],
    ['label'=>'Distribution Channels','value'=>mg_pi_num($distributionChannels),'detail'=>'Active source types connected to merchant distribution. More channels increase reach and awareness.','has_data'=>$distributionChannels > 0],
    ['label'=>'Distribution Events 30D','value'=>mg_pi_num($distributionEvents30),'detail'=>'Recent distribution source events received by the merchant.','has_data'=>$distributionEvents30 > 0],
    ['label'=>'Agent / AI Discoverable','value'=>mg_pi_num($agentDiscoverable),'detail'=>'Active campaigns and reward templates discoverable by agentic distribution.','has_data'=>$agentDiscoverable > 0],
    ['label'=>'Stamp Inventory','value'=>mg_pi_num($stampBalance),'detail'=>'Available stamp balance used as distribution capacity.','has_data'=>$stampBalance > 0],
    ['label'=>'Stamp Spend 30D','value'=>mg_pi_num($stampDebits30),'detail'=>'Recent stamp debits, treated as active distribution/awareness spend.','has_data'=>$stampDebits30 > 0],
    ['label'=>'Followers','value'=>mg_pi_num($followers),'detail'=>'Total profile followers included as follower growth.','has_data'=>$followers > 0],
    ['label'=>'Follower Momentum 30D','value'=>mg_pi_num($followerMomentum),'detail'=>'New followers minus unfollow/block actions detected in the last 30 days. Negative momentum lowers the score and ticker value.','has_data'=>$newFollowers30 > 0 || $lostFollowers30 > 0],
    ['label'=>'Value per New Follower','value'=>mg_pi_money($followerAddValue),'detail'=>'Estimated value added by one new Microgifter follower based on demand, distribution, stamps, and engagement.','has_data'=>$followerAddValue > 0],
    ['label'=>'Active Campaigns','value'=>mg_pi_num($activeCampaigns),'detail'=>'Campaigns currently marked active.','has_data'=>$activeCampaigns > 0],
    ['label'=>'Post Interactions','value'=>mg_pi_num($interactions),'detail'=>'Comments, reactions, shares, and saves across published posts.','has_data'=>$interactions > 0],
];
$formulas = [
    'Merchant Score = product depth + campaign velocity + redemption quality + engagement signal + commerce volume + distribution reach + stamp power + follower growth + follower momentum adjustment, capped at 100.',
    'Distribution Reach = active distribution programs + active distribution channels + recent distribution events/allocations + agent/AI discoverability.',
    'Stamp Power = stamp inventory + recent stamp spend + purchased stamp depth, capped at 8 score points.',
    'Follower Momentum Adjustment = new followers in 30 days minus detected unfollow/block actions; negative momentum lowers score.',
    'Value per New Follower = base value + product depth + active campaigns + distribution channels + AI discoverability + redemption rate + 30-day volume + engagement + stamp spend.',
    'Ticker Value = demand value + product value + campaign activity + distribution value + stamp inventory/spend value + engagement + follower brand value + follower momentum value + supporter value.',
];

$profileUrl = '/profile.php?slug=' . rawurlencode($slug);
$website = trim((string)($owner['website_url'] ?? ''));
$market = ['formula_version'=>'merchant_market_v2_distribution_stamps','ticker_symbol'=>$tickerSymbol,'ticker_value'=>mg_pi_money($tickerValue),'ticker_value_cents'=>$tickerValue,'merchant_score'=>$merchantScore,'rating'=>$rating,'confidence'=>$confidence,'has_data'=>$hasData,'follower_add_value'=>mg_pi_money($followerAddValue),'follower_add_value_cents'=>$followerAddValue,'follower_brand_value'=>mg_pi_money($followerBrandValue),'follower_brand_value_cents'=>$followerBrandValue,'follower_momentum'=>$followerMomentum,'follower_momentum_value'=>mg_pi_money($followerMomentumValue),'follower_momentum_value_cents'=>$followerMomentumValue,'distribution_value'=>mg_pi_money($distributionValue),'distribution_value_cents'=>$distributionValue,'stamp_inventory_value'=>mg_pi_money($stampInventoryValue),'stamp_inventory_value_cents'=>$stampInventoryValue,'stamp_spend_value'=>mg_pi_money($stampSpendValue),'stamp_spend_value_cents'=>$stampSpendValue,'components'=>['product_depth'=>round($productDepth,1),'campaign_velocity'=>round($campaignVelocity,1),'redemption_quality'=>round($redemptionQuality,1),'engagement_signal'=>round($engagementSignal,1),'commerce_volume'=>round($commerceVolume,1),'distribution_reach'=>round($distributionReach,1),'stamp_power'=>round($stampPower,1),'follower_growth'=>round($followerGrowth,1),'follower_momentum_adjustment'=>round($followerMomentumAdjustment,1)]];

header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie, Authorization');
mg_ok([
    'profile'=>['display_name'=>$display,'tagline'=>$tagline,'slug'=>$slug],
    'merchant_market'=>$market,
    'metrics'=>['ticker_symbol'=>mg_pi_metric($tickerSymbol,$tickerSymbol,$hasData),'ticker_value'=>mg_pi_metric(mg_pi_money($tickerValue),$tickerValue,$tickerValue>0),'merchant_score'=>mg_pi_metric((string)$merchantScore,$merchantScore,$hasData),'rating'=>mg_pi_metric($rating,$rating,$hasData),'confidence'=>mg_pi_metric(ucfirst($confidence),$confidence,$hasData),'distribution_channels'=>mg_pi_metric(mg_pi_num($distributionChannels),$distributionChannels,$distributionChannels>0),'stamp_inventory'=>mg_pi_metric(mg_pi_num($stampBalance),$stampBalance,$stampBalance>0),'stamp_spend_30d'=>mg_pi_metric(mg_pi_num($stampDebits30),$stampDebits30,$stampDebits30>0),'follower_momentum'=>mg_pi_metric(mg_pi_num($followerMomentum),$followerMomentum,$newFollowers30>0||$lostFollowers30>0),'value_per_follower'=>mg_pi_metric(mg_pi_money($followerAddValue),$followerAddValue,$followerAddValue>0),'active_drops'=>mg_pi_metric(mg_pi_num($activeDrops),$activeDrops,$activeDrops>0),'active_campaigns'=>mg_pi_metric(mg_pi_num($activeCampaigns),$activeCampaigns,$activeCampaigns>0),'demand_value'=>mg_pi_metric(mg_pi_money($demandValue),$demandValue,$demandValue>0),'floor_price'=>mg_pi_metric(mg_pi_money($floor),$floor,$floor>0),'volume_30d'=>mg_pi_metric(mg_pi_money($volume30),$volume30,$volume30>0),'redemption_rate'=>mg_pi_metric($issued30>0?mg_pi_pct($redemptionRate,0):'0%',$redemptionRate,$issued30>0),'demand_score'=>mg_pi_metric((string)$merchantScore,$merchantScore,$hasData),'demand_label'=>mg_pi_metric($rating,$merchantScore,$hasData),'market_growth_30d'=>mg_pi_metric($marketGrowth===null?'No trend':(($marketGrowth>=0?'▲ ':'▼ ').mg_pi_pct(abs($marketGrowth),1).' 30D'),$marketGrowth,$marketGrowth!==null),'posts_total'=>mg_pi_metric(mg_pi_num($postCount),$postCount,$postCount>0),'post_interactions'=>mg_pi_metric(mg_pi_num($interactions),$interactions,$interactions>0),'engagement_rate'=>mg_pi_metric($interactions>0?mg_pi_pct($engagementRate,1):'0%',$engagementRate,$interactions>0),'issued_30d'=>mg_pi_metric(mg_pi_num($issued30),$issued30,$issued30>0),'redeemed_30d'=>mg_pi_metric(mg_pi_num($redeemed30),$redeemed30,$redeemed30>0)],
    'factors'=>['products'=>(int)round($productDepth),'campaigns'=>(int)round($campaignVelocity),'redemptions'=>(int)round($redemptionQuality),'engagement'=>(int)round($engagementSignal),'commerce'=>(int)round($commerceVolume),'distribution'=>(int)round($distributionReach),'stamps'=>(int)round($stampPower),'followers'=>(int)round($followerGrowth),'momentum'=>(int)round($followerMomentumAdjustment)],
    'campaigns'=>['items'=>$campaignItems,'has_data'=>$campaignItems!==[],'total'=>$totalCampaigns,'active'=>$activeCampaigns,'issued'=>$campaignIssued,'capacity'=>$campaignCapacity,'events'=>$events],
    'distribution'=>['programs'=>$distributionPrograms,'active_programs'=>$activeDistributionPrograms,'channels'=>$distributionChannels,'connections'=>$distributionConnections,'events_30d'=>$distributionEvents30,'allocations_30d'=>$distributionAllocations30,'issued_value_30d_cents'=>$distributionIssuedValue30,'agent_discoverable'=>$agentDiscoverable],
    'stamps'=>['balance'=>$stampBalance,'purchased'=>$stampPurchased,'used'=>$stampUsed,'included'=>$stampIncluded,'debits_30d'=>$stampDebits30,'credits_30d'=>$stampCredits30],
    'followers'=>['total'=>$followers,'new_30d'=>$newFollowers30,'lost_30d'=>$lostFollowers30,'momentum_30d'=>$followerMomentum],
    'analytics'=>['items'=>$analytics,'has_data'=>array_reduce($analytics, static fn(bool $carry,array $item): bool => $carry || !empty($item['has_data']), false),'formulas'=>$formulas],
    'portfolio'=>['has_data'=>$tickerValue>0,'value'=>mg_pi_money($tickerValue),'subtitle'=>'Ticker value · '.$rating],
    'activity'=>['items'=>$activity,'has_data'=>$activity!==[]],
    'trending'=>['items'=>$trending,'has_data'=>$trending!==[]],
    'series'=>['volume_30d'=>$series,'score_components'=>[['component'=>'Product depth','score'=>round($productDepth,1)],['component'=>'Campaign velocity','score'=>round($campaignVelocity,1)],['component'=>'Redemption quality','score'=>round($redemptionQuality,1)],['component'=>'Engagement signal','score'=>round($engagementSignal,1)],['component'=>'Commerce volume','score'=>round($commerceVolume,1)],['component'=>'Distribution reach','score'=>round($distributionReach,1)],['component'=>'Stamp power','score'=>round($stampPower,1)],['component'=>'Follower growth','score'=>round($followerGrowth,1)],['component'=>'Follower momentum','score'=>round($followerMomentumAdjustment,1)]],'has_data'=>$series!==[]],
    'actions'=>['share_url'=>$profileUrl,'message_url'=>$website!==''?$website:null,'message_enabled'=>$website!=='','save_uses_follow'=>true],
    'source'=>$source,
]);

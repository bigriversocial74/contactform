<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/reviews-case-studies.php';
require_once dirname(__DIR__, 2) . '/profiles/_public_profile.php';

mg_require_method('GET');
$pdo = mg_db();
$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
if ($slug === '' || preg_match('/^[a-z0-9][a-z0-9-]{0,119}$/', $slug) !== 1) mg_fail('Invalid case study.', 422);

function mg_cs_scalar(PDO $pdo, string $sql, array $params = []): int|float {
    try { $stmt=$pdo->prepare($sql); $stmt->execute($params); $value=$stmt->fetchColumn(); return is_numeric($value)?$value+0:0; }
    catch (Throwable) { return 0; }
}
function mg_cs_rows(PDO $pdo, string $sql, array $params = []): array {
    try { $stmt=$pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
    catch (Throwable) { return []; }
}

try {
    $stmt=$pdo->prepare("SELECT id,public_id,user_id,slug,display_name,headline,bio,avatar_url,cover_url,location_label,website_url,profile_type FROM public_profiles WHERE slug=? AND visibility='public' AND status='active' LIMIT 1");
    $stmt->execute([$slug]); $profile=$stmt->fetch(PDO::FETCH_ASSOC); if(!$profile) mg_fail('Case study not found.',404);
    $merchantId=(int)$profile['user_id'];

    $productRows=mg_cs_rows($pdo,"SELECT cp.public_id,cp.slug,cp.product_type,cpv.title,cpv.description,cpv.unit_value_cents,cpv.currency,cover.public_id cover_asset_public_id FROM catalog_products cp INNER JOIN catalog_product_versions cpv ON cpv.id=cp.current_version_id AND cpv.version_status='published' LEFT JOIN catalog_product_version_assets pva ON pva.product_version_id=cpv.id AND pva.role='cover' LEFT JOIN catalog_assets cover ON cover.id=pva.asset_id AND cover.status='ready' WHERE cp.merchant_user_id=? AND cp.status='published' GROUP BY cp.id ORDER BY cp.published_at DESC,cp.id DESC LIMIT 8",[$merchantId]);
    $products=array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'slug'=>(string)$r['slug'],'title'=>(string)$r['title'],'description'=>(string)($r['description']??''),'product_type'=>(string)$r['product_type'],'amount'=>((int)$r['unit_value_cents'])/100,'price_label'=>strtoupper((string)$r['currency']).' '.number_format(((int)$r['unit_value_cents'])/100,2),'cover_url'=>mg_public_profile_media_url($r['cover_asset_public_id']??null)],$productRows);
    $campaigns=mg_cs_rows($pdo,"SELECT public_id,title,description,campaign_type,status,issued_count,starts_at,ends_at,updated_at FROM campaigns WHERE merchant_user_id=? AND status IN ('active','paused','ended') ORDER BY status='active' DESC,updated_at DESC LIMIT 8",[$merchantId]);

    $totalProducts=(int)mg_cs_scalar($pdo,"SELECT COUNT(*) FROM catalog_products WHERE merchant_user_id=? AND status='published'",[$merchantId]);
    $activeCampaigns=(int)mg_cs_scalar($pdo,"SELECT COUNT(*) FROM campaigns WHERE merchant_user_id=? AND status='active'",[$merchantId]);
    $totalSalesCents=(int)mg_cs_scalar($pdo,"SELECT COALESCE(SUM(amount_cents),0) FROM redemption_settlement_ledger WHERE merchant_user_id=? AND settlement_status NOT IN ('voided','reversed')",[$merchantId]);
    $totalClaims=(int)mg_cs_scalar($pdo,"SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND status IN ('claimed','redeemed')",[$merchantId]);
    $totalRedemptions=(int)mg_cs_scalar($pdo,"SELECT COUNT(*) FROM wallet_items WHERE merchant_user_id=? AND status='redeemed'",[$merchantId]);
    $newCustomers=(int)mg_cs_scalar($pdo,"SELECT COUNT(DISTINCT COALESCE(NULLIF(CAST(user_id AS CHAR),''),email)) FROM campaign_contacts WHERE merchant_user_id=? AND created_at>=DATE_SUB(CURDATE(),INTERVAL 30 DAY)",[$merchantId]);
    $redemptionRate=$totalClaims>0?round(($totalRedemptions/$totalClaims)*100,1):0.0;

    $salesRows=mg_cs_rows($pdo,"SELECT DATE(created_at) period,COALESCE(SUM(amount_cents),0) value FROM redemption_settlement_ledger WHERE merchant_user_id=? AND settlement_status NOT IN ('voided','reversed') AND created_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY period",[$merchantId]);
    $claimRows=mg_cs_rows($pdo,"SELECT DATE(updated_at) period,COUNT(*) value FROM wallet_items WHERE merchant_user_id=? AND status IN ('claimed','redeemed') AND updated_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(updated_at) ORDER BY period",[$merchantId]);
    $redemptionRows=mg_cs_rows($pdo,"SELECT DATE(redeemed_at) period,COUNT(*) value FROM wallet_items WHERE merchant_user_id=? AND status='redeemed' AND redeemed_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(redeemed_at) ORDER BY period",[$merchantId]);
    $dates=[]; for($i=6;$i>=0;$i--)$dates[]=(new DateTimeImmutable('today'))->modify('-'.$i.' days')->format('Y-m-d');
    $series=static function(array $rows,array $dates,bool $cents=false):array{$map=[];foreach($rows as $row)$map[(string)$row['period']]=(float)$row['value'];return array_map(static fn(string $date):float=>$cents?(($map[$date]??0)/100):($map[$date]??0),$dates);};

    $caseStmt=$pdo->prepare("SELECT * FROM featured_case_studies WHERE profile_id=? AND status='published' LIMIT 1"); $caseStmt->execute([(int)$profile['id']]); $case=$caseStmt->fetch(PDO::FETCH_ASSOC)?:[];
    $reviews=mg_cs_rows($pdo,"SELECT r.public_id,r.reviewer_name,r.rating,r.review_title,r.review_body,r.submitted_at,rr.reply_body FROM customer_reviews r LEFT JOIN customer_review_replies rr ON rr.review_id=r.id AND rr.status='published' WHERE r.merchant_user_id=? AND r.status='published' ORDER BY r.featured_in_case_study DESC,r.featured_on_profile DESC,r.submitted_at DESC LIMIT 6",[$merchantId]);

    mg_ok([
        'profile'=>['id'=>(string)$profile['public_id'],'slug'=>(string)$profile['slug'],'display_name'=>(string)$profile['display_name'],'headline'=>(string)($profile['headline']??''),'biography'=>(string)($profile['bio']??''),'avatar_url'=>$profile['avatar_url'],'cover_url'=>$profile['cover_url'],'location_label'=>(string)($profile['location_label']??''),'website_url'=>$profile['website_url'],'profile_type'=>(string)$profile['profile_type']],
        'counts'=>['published_products'=>$totalProducts,'active_campaigns'=>$activeCampaigns],
        'products'=>$products,'campaigns'=>$campaigns,
        'case_study_analytics'=>['total_sales'=>$totalSalesCents/100,'total_claims'=>$totalClaims,'total_redemptions'=>$totalRedemptions,'redemption_rate'=>$redemptionRate,'customer_growth'=>$newCustomers,'sales_series'=>$series($salesRows,$dates,true),'claims_series'=>$series($claimRows,$dates),'redemptions_series'=>$series($redemptionRows,$dates),'period_labels'=>$dates,'source'=>'database'],
        'case_study'=>['challenge'=>$case['challenge_text']??null,'solution'=>$case['solution_text']??null,'outcomes'=>mg_rcs_decode($case['outcomes_json']??null,[]),'title'=>$case['title']??null,'subtitle'=>$case['subtitle']??null],
        'reviews'=>array_map(static fn(array $r):array=>['id'=>(string)$r['public_id'],'reviewer_name'=>(string)$r['reviewer_name'],'rating'=>(int)$r['rating'],'title'=>$r['review_title'],'body'=>(string)$r['review_body'],'submitted_at'=>(string)$r['submitted_at'],'reply'=>$r['reply_body']?['body'=>(string)$r['reply_body']]:null],$reviews),
    ]);
} catch(Throwable $error) {
    mg_security_log('error','public.case_study.stats_failed','Unable to load case study statistics.',['slug'=>$slug,'message'=>$error->getMessage()],null);
    mg_fail('Unable to load case study.',500);
}

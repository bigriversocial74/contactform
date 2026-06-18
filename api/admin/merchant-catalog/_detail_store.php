<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_merchant_lifecycle.php';

function mg_admin_mc_storefront_detail(PDO $pdo,string $reference): array
{
    $store=mg_admin_mc_storefront($pdo,$reference,false);
    $workspace=mg_admin_mc_one($pdo,'SELECT public_id,display_name,status,eligibility_status,onboarding_percent FROM merchant_workspaces WHERE merchant_user_id=? LIMIT 1',[(int)$store['merchant_user_id']]);
    $user=mg_admin_mc_one($pdo,'SELECT id,email,display_name,full_name,status FROM users WHERE id=? LIMIT 1',[(int)$store['merchant_user_id']]);
    $draft=mg_storefront_revision($pdo,(int)$store['id'],'draft');
    $published=mg_storefront_revision($pdo,(int)$store['id'],'published');
    $draftProducts=$draft?mg_storefront_revision_products($pdo,(int)$draft['id']):[];
    $publishedProducts=$published?mg_storefront_revision_products($pdo,(int)$published['id']):[];
    $active=$draft?:$published;
    $management=$active?mg_storefront_revision_management($pdo,$active,(int)$store['merchant_user_id']):null;
    $readiness=mg_storefront_readiness($store,$management,$draft?$draftProducts:$publishedProducts);
    $revisions=mg_admin_mc_all($pdo,'SELECT public_id,version_number,revision_status,display_name,headline,published_at,created_by_user_id,created_at,updated_at FROM merchant_storefront_revisions WHERE storefront_id=? ORDER BY version_number DESC,id DESC LIMIT 50',[(int)$store['id']]);
    $issues=[];
    foreach($readiness['checks'] as $check){if(!empty($check['required'])&&empty($check['complete']))$issues[]=['key'=>(string)$check['key'],'label'=>(string)$check['label'],'severity'=>'high'];}
    if((string)$store['status']==='suspended')$issues[]=['key'=>'suspended','label'=>'Storefront is suspended','severity'=>'urgent'];
    if(!$published)$issues[]=['key'=>'published_revision','label'=>'No published storefront revision exists','severity'=>'normal'];
    return [
        'entity'=>['type'=>'storefront','public_id'=>(string)$store['public_id'],'status'=>(string)$store['status'],'secondary_status'=>(string)($workspace['status']??''),'title'=>(string)$store['display_name'],'merchant_user_id'=>(int)$store['merchant_user_id'],'merchant'=>$user,'created_at'=>(string)$store['created_at'],'updated_at'=>(string)$store['updated_at']],
        'facts'=>[mg_admin_mc_fact('Slug',(string)$store['slug']),mg_admin_mc_fact('Headline',$store['headline']),mg_admin_mc_fact('Published at',$store['published_at'],'date'),mg_admin_mc_fact('Readiness score',(int)$readiness['score'],'percent'),mg_admin_mc_fact('Can publish',(bool)$readiness['can_publish'],'boolean'),mg_admin_mc_fact('Workspace',$workspace['public_id']??null)],
        'issues'=>$issues,'readiness'=>$readiness,'related'=>['workspace'=>$workspace,'draft'=>$draft,'published'=>$published,'revisions'=>$revisions,'draft_products'=>$draftProducts,'published_products'=>$publishedProducts],
    ];
}

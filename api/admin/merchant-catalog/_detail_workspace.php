<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/_merchant_lifecycle.php';

function mg_admin_mc_workspace_detail(PDO $pdo,string $reference): array
{
    $workspace=mg_admin_mc_workspace($pdo,$reference,false);
    $user=mg_admin_mc_one($pdo,'SELECT id,email,display_name,full_name,status,email_verified_at,created_at,updated_at FROM users WHERE id=? LIMIT 1',[(int)$workspace['merchant_user_id']]);
    $profile=mg_admin_mc_one($pdo,'SELECT public_id,slug,display_name,headline,visibility,status,created_at,updated_at FROM public_profiles WHERE user_id=? LIMIT 1',[(int)$workspace['merchant_user_id']]);
    $storefront=mg_admin_mc_one($pdo,'SELECT public_id,slug,display_name,status,published_at,created_at,updated_at FROM merchant_storefronts WHERE merchant_user_id=? LIMIT 1',[(int)$workspace['merchant_user_id']]);
    $locations=mg_admin_mc_all($pdo,'SELECT public_id,name,location_code,city,region,postal_code,country_code,status,is_primary,created_at,updated_at FROM merchant_locations WHERE workspace_id=? ORDER BY is_primary DESC,name,id LIMIT 100',[(int)$workspace['id']]);
    $team=mg_admin_mc_all($pdo,'SELECT public_id,user_id,display_name,role_key,status,invited_at,accepted_at,removed_at,created_at,updated_at FROM merchant_team_members WHERE workspace_id=? ORDER BY status,role_key,id LIMIT 100',[(int)$workspace['id']]);
    $onboarding=mg_admin_mc_all($pdo,'SELECT step_key,step_order,status,completed_at,completed_by_user_id,updated_at FROM merchant_onboarding_steps WHERE workspace_id=? ORDER BY step_order,id LIMIT 100',[(int)$workspace['id']]);
    $payment=mg_admin_mc_one($pdo,'SELECT provider_key,mode,account_connected,identity_verified,charges_enabled,payouts_enabled,tax_setup_complete,test_payment_complete,live_approved,updated_at FROM merchant_payment_readiness WHERE workspace_id=? LIMIT 1',[(int)$workspace['id']]);
    $products=mg_admin_mc_all($pdo,'SELECT p.public_id,p.slug,p.product_type,p.status,p.published_at,p.archived_at,p.created_at,p.updated_at,COALESCE(v.title,p.slug) title,v.unit_value_cents,v.currency FROM catalog_products p LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id WHERE p.merchant_user_id=? ORDER BY p.updated_at DESC,p.id DESC LIMIT 100',[(int)$workspace['merchant_user_id']]);
    $assets=mg_admin_mc_all($pdo,'SELECT public_id,asset_type,status,original_filename,mime_type,byte_size,created_at,updated_at FROM catalog_assets WHERE owner_user_id=? ORDER BY updated_at DESC,id DESC LIMIT 100',[(int)$workspace['merchant_user_id']]);
    $issues=[];
    if((string)$workspace['status']!=='active')$issues[]=['key'=>'workspace_status','label'=>'Workspace is not active','severity'=>'high'];
    if(!in_array((string)$workspace['eligibility_status'],['eligible','manual_review'],true))$issues[]=['key'=>'eligibility','label'=>'Eligibility is incomplete','severity'=>'high'];
    if((int)$workspace['onboarding_percent']<100)$issues[]=['key'=>'onboarding','label'=>'Onboarding is incomplete','severity'=>'normal'];
    if(!$profile||(string)$profile['status']!=='active')$issues[]=['key'=>'profile','label'=>'Public merchant profile is unavailable','severity'=>'normal'];
    if(!$storefront)$issues[]=['key'=>'storefront','label'=>'No storefront exists','severity'=>'normal'];
    if(count(array_filter($locations,static fn(array $row):bool=>$row['status']==='active'))===0)$issues[]=['key'=>'locations','label'=>'No active merchant location exists','severity'=>'high'];
    return [
        'entity'=>['type'=>'workspace','public_id'=>(string)$workspace['public_id'],'status'=>(string)$workspace['status'],'secondary_status'=>(string)$workspace['eligibility_status'],'title'=>(string)$workspace['display_name'],'merchant_user_id'=>(int)$workspace['merchant_user_id'],'merchant'=>$user,'created_at'=>(string)$workspace['created_at'],'updated_at'=>(string)$workspace['updated_at']],
        'facts'=>[mg_admin_mc_fact('Onboarding',(int)$workspace['onboarding_percent'],'percent'),mg_admin_mc_fact('Business type',$workspace['business_type']),mg_admin_mc_fact('Website',$workspace['website_url']),mg_admin_mc_fact('Support email',$workspace['support_email']),mg_admin_mc_fact('Support phone',$workspace['support_phone']),mg_admin_mc_fact('Currency',(string)$workspace['default_currency']),mg_admin_mc_fact('Timezone',(string)$workspace['timezone']),mg_admin_mc_fact('Activated at',$workspace['activated_at'],'date')],
        'issues'=>$issues,'related'=>compact('profile','storefront','locations','team','onboarding','payment','products','assets'),
    ];
}

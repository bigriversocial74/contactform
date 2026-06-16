<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit('Not found.');}

require_once dirname(__DIR__).'/api/profiles/_public_profile.php';
require_once dirname(__DIR__).'/tests/integration/MicrogiftBehaviorFixture.php';

function mg_pp_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_pp_unavailable(callable $callback,string $message): void
{
    try{$callback();}catch(RuntimeException|InvalidArgumentException){return;}
    throw new RuntimeException($message);
}

function mg_pp_profile(PDO $pdo,int $userId,string $slug,string $visibility='public',string $status='active'): array
{
    $now=gmdate('Y-m-d H:i:s');$publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'public_profiles',[
        'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug,'display_name'=>'Profile '.$slug,
        'headline'=>'Headline '.$slug,'bio'=>'Biography '.$slug,'avatar_url'=>'/assets/profile/'.$slug.'.png',
        'cover_url'=>'https://cdn.example.test/'.$slug.'/cover.jpg','location_label'=>'Phoenix, AZ',
        'website_url'=>'https://example.test/'.$slug,'profile_type'=>'creator','visibility'=>$visibility,
        'status'=>$status,'completion_score'=>90,'metadata_json'=>json_encode(['private_note'=>'never-public'],JSON_THROW_ON_ERROR),
        'published_at'=>$status==='active'?$now:null,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug];
}

function mg_pp_asset(PDO $pdo,int $ownerId,string $runId,string $suffix): array
{
    $now=gmdate('Y-m-d H:i:s');$publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'catalog_assets',[
        'public_id'=>$publicId,'owner_user_id'=>$ownerId,'asset_type'=>'image','storage_provider'=>'private_local',
        'storage_key'=>'profile-validation/'.$runId.'/'.$suffix.'.jpg','original_filename'=>$suffix.'.jpg',
        'mime_type'=>'image/jpeg','byte_size'=>128,'checksum_sha256'=>hash('sha256',$runId.$suffix),
        'status'=>'ready','metadata_json'=>json_encode(['provider_secret'=>'never-public'],JSON_THROW_ON_ERROR),
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId];
}

function mg_pp_product(PDO $pdo,int $ownerId,string $runId,string $suffix,string $status='published'): array
{
    $now=gmdate('Y-m-d H:i:s');$productPublic=mg_public_uuid();
    $productId=mg_it_insert($pdo,'catalog_products',[
        'public_id'=>$productPublic,'merchant_user_id'=>$ownerId,'product_type'=>'gift','slug'=>'profile-'.$runId.'-'.$suffix,
        'status'=>$status,'current_version_id'=>null,'created_by_user_id'=>$ownerId,
        'published_at'=>$status==='published'?$now:null,'archived_at'=>$status==='archived'?$now:null,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $versionPublic=mg_public_uuid();
    $versionStatus=$status==='published'?'published':'draft';
    $versionId=mg_it_insert($pdo,'catalog_product_versions',[
        'public_id'=>$versionPublic,'product_id'=>$productId,'version_number'=>1,'version_status'=>$versionStatus,
        'title'=>'Product '.$suffix,'description'=>'Safe product '.$suffix,'unit_value_cents'=>1000,'currency'=>'USD',
        'metadata_json'=>json_encode(['provider_price_id'=>'price_private_'.$suffix],JSON_THROW_ON_ERROR),
        'checksum'=>hash('sha256',$runId.$suffix),'created_by_user_id'=>$ownerId,
        'published_at'=>$versionStatus==='published'?$now:null,'created_at'=>$now,
    ]);
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    $asset=mg_pp_asset($pdo,$ownerId,$runId,'cover-'.$suffix);
    mg_it_insert($pdo,'catalog_product_version_assets',[
        'product_version_id'=>$versionId,'asset_id'=>$asset['id'],'role'=>'cover','sort_order'=>0,'created_at'=>$now,
    ]);
    return ['id'=>$productId,'public_id'=>$productPublic,'version_id'=>$versionId,'version_public_id'=>$versionPublic,'slug'=>'profile-'.$runId.'-'.$suffix];
}

function mg_pp_post(PDO $pdo,int $ownerId,string $suffix,string $visibility='public',string $status='published',string $moderation='clear',?int $planId=null): array
{
    $now=gmdate('Y-m-d H:i:s');$publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'feed_posts',[
        'public_id'=>$publicId,'merchant_user_id'=>$ownerId,'catalog_product_id'=>null,
        'linked_microgift_instance_id'=>null,'subscription_plan_id'=>$planId,'current_version_id'=>null,
        'post_type'=>'simple','headline'=>'Post '.$suffix,'body'=>'Body '.$suffix,
        'media_json'=>json_encode([['url'=>'https://cdn.example.test/posts/'.$suffix.'.jpg','type'=>'image','provider_key'=>'never-public']],JSON_THROW_ON_ERROR),
        'visibility'=>$visibility,'status'=>$status,'moderation_status'=>$moderation,
        'comment_count'=>1,'reaction_count'=>2,'share_count'=>3,'save_count'=>4,
        'created_by_user_id'=>$ownerId,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId];
}

function mg_pp_plan(PDO $pdo,int $ownerId,string $profilePublicId,string $suffix,string $status='active'): array
{
    $now=gmdate('Y-m-d H:i:s');$publicId=mg_public_uuid();
    $id=mg_it_insert($pdo,'subscription_plans',[
        'public_id'=>$publicId,'owner_user_id'=>$ownerId,'target_type'=>'profile','target_reference'=>$profilePublicId,
        'name'=>'Plan '.$suffix,'description'=>'Plan description '.$suffix,'amount_cents'=>1200,'currency'=>'USD',
        'interval_unit'=>'month','interval_count'=>1,'trial_days'=>7,'funding_type'=>'stripe','status'=>$status,
        'provider_price_id'=>'price_private_'.$suffix,'policy_json'=>json_encode(['private'=>true],JSON_THROW_ON_ERROR),
        'metadata_json'=>json_encode(['provider_customer_id'=>'cus_private_'.$suffix],JSON_THROW_ON_ERROR),
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$id,'public_id'=>$publicId];
}

function mg_pp_subscription(PDO $pdo,int $planId,int $subscriberId,int $ownerId,string $profilePublicId,string $suffix,string $status='active',string $recovery='clear',string $periodEnd='+30 days'): int
{
    $now=gmdate('Y-m-d H:i:s');$end=gmdate('Y-m-d H:i:s',strtotime($periodEnd));
    return mg_it_insert($pdo,'subscriptions',[
        'public_id'=>mg_public_uuid(),'plan_id'=>$planId,'subscriber_user_id'=>$subscriberId,'recipient_user_id'=>$ownerId,
        'target_type'=>'profile','target_reference'=>$profilePublicId,'amount_cents'=>1200,'currency'=>'USD','funding_type'=>'stripe',
        'status'=>$status,'idempotency_key'=>'profile-validation-'.$suffix,'provider_subscription_id'=>'sub_private_'.$suffix,
        'provider_customer_id'=>'cus_private_'.$suffix,'provider_payment_method_ref'=>'pm_private_'.$suffix,
        'current_period_start'=>$now,'current_period_end'=>$end,'next_billing_at'=>$status==='active'?$end:null,
        'trial_ends_at'=>null,'initial_payment_required'=>0,'funded_at'=>$now,'activated_at'=>$now,
        'cancel_at_period_end'=>0,'retry_count'=>0,'recovery_status'=>$recovery,
        'metadata_json'=>json_encode(['wallet_state'=>'never-public'],JSON_THROW_ON_ERROR),
        'created_at'=>$now,'updated_at'=>$now,
    ]);
}

function mg_pp_forbidden_keys(mixed $value,array &$keys): void
{
    if(!is_array($value))return;
    foreach($value as $key=>$child){$keys[]=(string)$key;mg_pp_forbidden_keys($child,$keys);}
}

$pdo=mg_db();$runId='publicprofile'.bin2hex(random_bytes(5));
$summary=array_fill_keys([
    'anonymous_public','anonymous_non_public_hidden','unlisted_direct_only','owner_preview_authenticated',
    'blocked_non_enumerating','inactive_components_excluded','products_filtered','posts_moderated',
    'follower_visibility','subscriber_visibility','recovery_revokes_access','expired_canceled_revoke_access',
    'tip_eligibility','no_private_data','stable_pagination','read_side_effect_free','bounded_queries','canonical_authorities',
],false);

$pdo->beginTransaction();
try{
    $owner=mg_it_user($pdo,$runId.'-owner@example.test','Public Profile Owner');
    $follower=mg_it_user($pdo,$runId.'-follower@example.test','Follower Viewer');
    $subscriber=mg_it_user($pdo,$runId.'-subscriber@example.test','Subscriber Viewer');
    $expired=mg_it_user($pdo,$runId.'-expired@example.test','Expired Viewer');
    $blocked=mg_it_user($pdo,$runId.'-blocked@example.test','Blocked Viewer');
    $privateOwner=mg_it_user($pdo,$runId.'-private@example.test','Private Owner');
    $hiddenOwner=mg_it_user($pdo,$runId.'-hidden@example.test','Hidden Owner');
    $suspendedOwner=mg_it_user($pdo,$runId.'-suspended@example.test','Suspended Owner');
    $draftOwner=mg_it_user($pdo,$runId.'-draft@example.test','Draft Owner');
    $unlistedOwner=mg_it_user($pdo,$runId.'-unlisted@example.test','Unlisted Owner');

    $profile=mg_pp_profile($pdo,$owner,'profile-'.$runId);
    $privateProfile=mg_pp_profile($pdo,$privateOwner,'private-'.$runId,'private','active');
    $hiddenProfile=mg_pp_profile($pdo,$hiddenOwner,'hidden-'.$runId,'public','hidden');
    $suspendedProfile=mg_pp_profile($pdo,$suspendedOwner,'suspended-'.$runId,'public','suspended');
    $draftProfile=mg_pp_profile($pdo,$draftOwner,'draft-'.$runId,'private','draft');
    $unlistedProfile=mg_pp_profile($pdo,$unlistedOwner,'unlisted-'.$runId,'unlisted','active');

    mg_it_insert($pdo,'public_profile_links',['public_id'=>mg_public_uuid(),'profile_id'=>$profile['id'],'label'=>'Website','url'=>'https://example.test/public','link_type'=>'website','sort_order'=>10,'is_active'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
    mg_it_insert($pdo,'public_profile_links',['public_id'=>mg_public_uuid(),'profile_id'=>$profile['id'],'label'=>'Unsafe','url'=>'javascript:alert(1)','link_type'=>'custom','sort_order'=>20,'is_active'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]);
    mg_it_insert($pdo,'public_profile_links',['public_id'=>mg_public_uuid(),'profile_id'=>$profile['id'],'label'=>'Inactive','url'=>'https://example.test/inactive','link_type'=>'custom','sort_order'=>30,'is_active'=>0,'created_at'=>gmdate('Y-m-d H:i:s')]);
    mg_it_insert($pdo,'public_profile_sections',['public_id'=>mg_public_uuid(),'profile_id'=>$profile['id'],'section_type'=>'about','title'=>'About','body'=>'Public body','sort_order'=>10,'is_active'=>1,'metadata_json'=>json_encode(['admin'=>'private']),'created_at'=>gmdate('Y-m-d H:i:s')]);
    mg_it_insert($pdo,'public_profile_sections',['public_id'=>mg_public_uuid(),'profile_id'=>$profile['id'],'section_type'=>'draft','title'=>'Draft','body'=>'Hidden body','sort_order'=>20,'is_active'=>0,'metadata_json'=>null,'created_at'=>gmdate('Y-m-d H:i:s')]);

    $now=gmdate('Y-m-d H:i:s');
    $storeId=mg_it_insert($pdo,'merchant_storefronts',['public_id'=>mg_public_uuid(),'merchant_user_id'=>$owner,'slug'=>'store-'.$runId,'display_name'=>'Profile Store','headline'=>'Store headline','description'=>'Store description','status'=>'published','published_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
    $revisionId=mg_it_insert($pdo,'merchant_storefront_revisions',['public_id'=>mg_public_uuid(),'storefront_id'=>$storeId,'version_number'=>1,'revision_status'=>'published','display_name'=>'Profile Store','headline'=>'Published headline','description'=>'Published description','checksum'=>hash('sha256',$runId.'store'),'published_at'=>$now,'created_by_user_id'=>$owner,'created_at'=>$now,'updated_at'=>$now]);
    mg_it_insert($pdo,'merchant_storefront_states',['storefront_id'=>$storeId,'published_revision_id'=>$revisionId,'updated_at'=>$now]);

    $productA=mg_pp_product($pdo,$owner,$runId,'a','published');
    $productB=mg_pp_product($pdo,$owner,$runId,'b','published');
    $productC=mg_pp_product($pdo,$owner,$runId,'c','published');
    $draftProduct=mg_pp_product($pdo,$owner,$runId,'draft','draft');
    $archivedProduct=mg_pp_product($pdo,$owner,$runId,'archived','archived');
    foreach([[$productA,1,10,'visible'],[$productB,0,20,'visible'],[$productC,0,30,'visible'],[$draftProduct,0,40,'visible'],[$archivedProduct,0,50,'visible']] as [$product,$featured,$sort,$visibility]){
        mg_it_insert($pdo,'merchant_storefront_revision_products',['storefront_revision_id'=>$revisionId,'catalog_product_id'=>$product['id'],'sort_order'=>$sort,'is_featured'=>$featured,'visibility'=>$visibility,'created_at'=>$now,'updated_at'=>$now]);
    }
    $hiddenPlacement=mg_pp_product($pdo,$owner,$runId,'hidden-placement','published');
    mg_it_insert($pdo,'merchant_storefront_revision_products',['storefront_revision_id'=>$revisionId,'catalog_product_id'=>$hiddenPlacement['id'],'sort_order'=>60,'is_featured'=>0,'visibility'=>'hidden','created_at'=>$now,'updated_at'=>$now]);

    $plan=mg_pp_plan($pdo,$owner,$profile['public_id'],'active','active');
    mg_pp_plan($pdo,$owner,$profile['public_id'],'draft','draft');
    mg_pp_subscription($pdo,$plan['id'],$subscriber,$owner,$profile['public_id'],'active','active','clear','+30 days');
    mg_pp_subscription($pdo,$plan['id'],$expired,$owner,$profile['public_id'],'expired','canceled','clear','-1 day');

    mg_pp_post($pdo,$owner,'public-1','public');
    mg_pp_post($pdo,$owner,'public-2','public');
    mg_pp_post($pdo,$owner,'public-3','public');
    $followerPost=mg_pp_post($pdo,$owner,'followers','followers');
    $subscriberPost=mg_pp_post($pdo,$owner,'subscribers','subscribers','published','clear',$plan['id']);
    mg_pp_post($pdo,$owner,'removed','public','published','removed');
    mg_pp_post($pdo,$owner,'hidden','public','published','hidden');
    mg_pp_post($pdo,$owner,'draft','public','draft','clear');
    mg_it_insert($pdo,'social_follows',['follower_user_id'=>$follower,'followed_user_id'=>$owner,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);
    mg_it_insert($pdo,'social_blocks',['blocking_user_id'=>$owner,'blocked_user_id'=>$blocked,'created_at'=>$now]);

    $anonymous=mg_public_profile_read($pdo,$profile['slug'],['product_limit'=>2,'post_limit'=>2,'plan_limit'=>2]);
    mg_pp_assert($anonymous['profile']['id']===$profile['public_id'],'Anonymous public profile identity failed.');
    $summary['anonymous_public']=true;

    foreach([$privateProfile,$hiddenProfile,$suspendedProfile,$draftProfile] as $nonPublic){
        mg_pp_unavailable(fn()=>mg_public_profile_read($pdo,$nonPublic['slug']), 'Non-public profile was enumerable.');
    }
    $summary['anonymous_non_public_hidden']=true;

    $unlistedRead=mg_public_profile_read($pdo,$unlistedProfile['slug']);
    mg_pp_assert($unlistedRead['profile']['visibility']==='unlisted'&&!isset($unlistedRead['discovery']),'Unlisted direct access/discovery boundary failed.');
    $summary['unlisted_direct_only']=true;

    mg_pp_unavailable(fn()=>mg_public_profile_read($pdo,$privateProfile['slug'],['viewer_id'=>$privateOwner]),'Private profile leaked without explicit preview.');
    $preview=mg_public_profile_read($pdo,$privateProfile['slug'],['viewer_id'=>$privateOwner,'preview'=>true]);
    $draftPreview=mg_public_profile_read($pdo,$draftProfile['slug'],['viewer_id'=>$draftOwner,'preview'=>true]);
    mg_pp_assert($preview['profile']['availability']['is_preview']&&$draftPreview['profile']['availability']['is_preview'],'Owner preview failed.');
    $summary['owner_preview_authenticated']=true;

    mg_pp_unavailable(fn()=>mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$blocked]),'Blocked viewer accessed profile.');
    $summary['blocked_non_enumerating']=true;

    mg_pp_assert(count($anonymous['links'])===1&&$anonymous['links'][0]['label']==='Website','Inactive or unsafe links leaked.');
    mg_pp_assert(count($anonymous['sections'])===1&&$anonymous['sections'][0]['title']==='About','Inactive sections leaked.');
    $summary['inactive_components_excluded']=true;

    $firstProducts=$anonymous['products'];
    mg_pp_assert(count($firstProducts['items'])===2&&$firstProducts['has_more']&&is_string($firstProducts['next_cursor']),'First product page failed.');
    $secondProducts=mg_public_profile_read($pdo,$profile['slug'],['product_limit'=>2,'product_cursor'=>$firstProducts['next_cursor']])['products'];
    $productIds=array_merge(array_column($firstProducts['items'],'id'),array_column($secondProducts['items'],'id'));
    mg_pp_assert(count($productIds)===3&&count(array_unique($productIds))===3,'Product pagination duplicated, skipped, or leaked unpublished products.');
    mg_pp_assert(!in_array($draftProduct['public_id'],$productIds,true)&&!in_array($archivedProduct['public_id'],$productIds,true)&&!in_array($hiddenPlacement['public_id'],$productIds,true),'Unavailable products leaked.');
    $summary['products_filtered']=true;$summary['stable_pagination']=true;

    $anonPostIds=array_column($anonymous['posts']['items'],'id');
    mg_pp_assert(!in_array($followerPost['public_id'],$anonPostIds,true)&&!in_array($subscriberPost['public_id'],$anonPostIds,true),'Restricted posts leaked anonymously.');
    $summary['posts_moderated']=true;

    $followerRead=mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$follower,'post_limit'=>12]);
    mg_pp_assert(in_array($followerPost['public_id'],array_column($followerRead['posts']['items'],'id'),true),'Follower-only post unavailable to follower.');
    $summary['follower_visibility']=true;

    $subscriberRead=mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$subscriber,'post_limit'=>12]);
    mg_pp_assert(in_array($subscriberPost['public_id'],array_column($subscriberRead['posts']['items'],'id'),true),'Subscriber-only post unavailable to active subscriber.');
    mg_pp_assert((int)$subscriberRead['social_counts']['supporters']===1,'Eligible active supporter count failed.');
    $summary['subscriber_visibility']=true;

    foreach(['disputed','refunded','chargeback'] as $recoveryState){
        $pdo->prepare('UPDATE subscriptions SET recovery_status=?,access_suspended_at=NOW() WHERE subscriber_user_id=? AND recipient_user_id=?')->execute([$recoveryState,$subscriber,$owner]);
        $recoveryRead=mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$subscriber,'post_limit'=>12]);
        mg_pp_assert(!in_array($subscriberPost['public_id'],array_column($recoveryRead['posts']['items'],'id'),true),$recoveryState.' recovery did not revoke subscriber access.');
        mg_pp_assert((int)$recoveryRead['social_counts']['supporters']===0,$recoveryState.' recovery did not revoke supporter count eligibility.');
    }
    $summary['recovery_revokes_access']=true;

    $expiredRead=mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$expired,'post_limit'=>12]);
    mg_pp_assert(!in_array($subscriberPost['public_id'],array_column($expiredRead['posts']['items'],'id'),true),'Expired/canceled subscription granted access.');
    $summary['expired_canceled_revoke_access']=true;

    mg_pp_assert($anonymous['tip']['available']===true&&$anonymous['tip']['target']['id']===$profile['public_id'],'Public tip capability failed.');
    mg_pp_assert(mg_public_profile_read($pdo,$profile['slug'],['viewer_id'=>$owner])['tip']['available']===false,'Self-tip capability was exposed.');
    mg_pp_assert(mg_tip_public_profile_capability($pdo,$suspendedProfile['public_id'])['available']===false,'Suspended recipient remained tip-eligible.');
    $summary['tip_eligibility']=true;

    $keys=[];mg_pp_forbidden_keys($anonymous,$keys);
    foreach(['user_id','profile_id','merchant_user_id','owner_user_id','recipient_user_id','email','phone','metadata','metadata_json','provider_price_id','provider_customer_id','provider_payment_method_ref','wallet','ledger'] as $forbidden){
        mg_pp_assert(!in_array($forbidden,$keys,true),'Forbidden key leaked: '.$forbidden);
    }
    $encoded=json_encode($anonymous,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    foreach(['price_private_','cus_private_','pm_private_','never-public'] as $secret)mg_pp_assert(!str_contains($encoded,$secret),'Private provider or metadata value leaked.');
    $summary['no_private_data']=true;

    $tables=['audit_logs','events','security_logs','operational_alerts','user_sessions'];$before=[];
    foreach($tables as $table)$before[$table]=(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM `'.$table.'`');
    $stableOne=mg_public_profile_read($pdo,$profile['slug'],['product_limit'=>2,'post_limit'=>2,'plan_limit'=>2]);$queriesOne=mg_public_profile_query_count();
    $stableTwo=mg_public_profile_read($pdo,$profile['slug'],['product_limit'=>2,'post_limit'=>2,'plan_limit'=>2]);$queriesTwo=mg_public_profile_query_count();
    mg_pp_assert($stableOne===$stableTwo,'Repeated reads were not stable.');
    foreach($tables as $table)mg_pp_assert($before[$table]===(int)mg_it_scalar($pdo,'SELECT COUNT(*) FROM `'.$table.'`'),'Read created side effects in '.$table.'.');
    $summary['read_side_effect_free']=true;
    mg_pp_assert($queriesOne<20&&$queriesTwo===$queriesOne,'Query count was unbounded or unstable.');
    $summary['bounded_queries']=true;

    $socialSource=file_get_contents(dirname(__DIR__).'/api/social/_social.php');
    $profileSource=file_get_contents(dirname(__DIR__).'/api/profiles/_public_profile.php');
    $endpointSource=file_get_contents(dirname(__DIR__).'/api/public/profile.php');
    mg_pp_assert(is_string($socialSource)&&str_contains($socialSource,'mg_social_can_view(')&&str_contains($profileSource,'mg_social_can_view('),'Canonical social authority not reused.');
    mg_pp_assert(str_contains($profileSource,'mg_tip_public_profile_capability(')&&str_contains($profileSource,'mg_storefront_owned('),'Canonical tip/storefront authority not reused.');
    mg_pp_assert(is_string($endpointSource)&&str_contains($endpointSource,'mg_public_profile_read(')&&!is_file(dirname(__DIR__).'/api/profiles/public.php'),'Canonical public endpoint was not consolidated.');
    $summary['canonical_authorities']=true;

    $pdo->rollBack();
    echo json_encode($summary+['suite'=>'canonical_public_profile_read_contract','queries_per_read'=>$queriesOne],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){
    if($pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL);
    exit(1);
}

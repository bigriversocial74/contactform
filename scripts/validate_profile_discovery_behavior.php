<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Not found.'); }
require_once dirname(__DIR__) . '/api/profiles/_discovery.php';
require_once dirname(__DIR__) . '/tests/integration/MicrogiftBehaviorFixture.php';

function pd_assert(bool $ok, string $name): void { if (!$ok) throw new RuntimeException('Discovery validation failed: ' . $name); }
function pd_profile(PDO $pdo, int $userId, string $slug, string $visibility='public', string $status='active', string $type='creator'): array {
    $now=gmdate('Y-m-d H:i:s'); $publicId=mg_public_uuid();
    mg_it_insert($pdo,'public_profiles',[
        'public_id'=>$publicId,'user_id'=>$userId,'slug'=>$slug,'display_name'=>'Profile '.$slug,
        'headline'=>'Phoenix '.$type.' '.$slug,'bio'=>'Profile biography','avatar_url'=>null,'cover_url'=>null,
        'location_label'=>'Phoenix, AZ','website_url'=>null,'profile_type'=>$type,'visibility'=>$visibility,
        'status'=>$status,'completion_score'=>90,'metadata_json'=>null,'published_at'=>$status==='active'?$now:null,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['public_id'=>$publicId,'slug'=>$slug];
}
function pd_product(PDO $pdo, int $ownerId, string $runId): void {
    $now=gmdate('Y-m-d H:i:s');
    $productId=mg_it_insert($pdo,'catalog_products',[
        'public_id'=>mg_public_uuid(),'merchant_user_id'=>$ownerId,'product_type'=>'gift','slug'=>'gift-'.$runId,
        'status'=>'published','current_version_id'=>null,'created_by_user_id'=>$ownerId,'published_at'=>$now,
        'archived_at'=>null,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $versionId=mg_it_insert($pdo,'catalog_product_versions',[
        'public_id'=>mg_public_uuid(),'product_id'=>$productId,'version_number'=>1,'version_status'=>'published',
        'title'=>'Gift category product','description'=>'Published gift product','unit_value_cents'=>1500,'currency'=>'USD',
        'metadata_json'=>null,'checksum'=>hash('sha256',$runId),'created_by_user_id'=>$ownerId,'published_at'=>$now,'created_at'=>$now,
    ]);
    $pdo->prepare('UPDATE catalog_products SET current_version_id=? WHERE id=?')->execute([$versionId,$productId]);
    mg_it_insert($pdo,'merchant_storefronts',[
        'public_id'=>mg_public_uuid(),'merchant_user_id'=>$ownerId,'slug'=>'store-'.$runId,'display_name'=>'Discovery Store',
        'headline'=>'Store headline','description'=>'Store description','status'=>'published','published_at'=>$now,
        'created_at'=>$now,'updated_at'=>$now,
    ]);
}
function pd_keys(mixed $value, array &$keys): void {
    if (!is_array($value)) return;
    foreach ($value as $key=>$child) { $keys[]=strtolower((string)$key); pd_keys($child,$keys); }
}

$pdo=mg_db(); $runId='pd'.bin2hex(random_bytes(5));
$result=array_fill_keys(['public_and_unlisted','visibility_exclusions','blocked_exclusion','deterministic_ranking','stable_cursor','wildcard_safety','filters','safe_projection','curated_separation','rollback_clean'],false);
$pdo->beginTransaction();
try {
    $viewer=mg_it_user($pdo,$runId.'-viewer@example.test','Discovery Viewer');
    $alphaUser=mg_it_user($pdo,$runId.'-alpha@example.test','Alpha Owner');
    $betaUser=mg_it_user($pdo,$runId.'-beta@example.test','Beta Owner');
    $unlistedUser=mg_it_user($pdo,$runId.'-unlisted@example.test','Unlisted Owner');
    $privateUser=mg_it_user($pdo,$runId.'-private@example.test','Private Owner');
    $hiddenUser=mg_it_user($pdo,$runId.'-hidden@example.test','Hidden Owner');
    $suspendedUser=mg_it_user($pdo,$runId.'-suspended@example.test','Suspended Owner');
    $inactiveUser=mg_it_user($pdo,$runId.'-inactive@example.test','Inactive Owner');
    $blockedUser=mg_it_user($pdo,$runId.'-blocked@example.test','Blocked Owner');
    $pdo->prepare("UPDATE users SET status='disabled' WHERE id=?")->execute([$inactiveUser]);

    $alpha=pd_profile($pdo,$alphaUser,'alpha-'.$runId,'public','active','merchant');
    $beta=pd_profile($pdo,$betaUser,'beta-'.$runId);
    $unlisted=pd_profile($pdo,$unlistedUser,'unlisted-'.$runId,'unlisted');
    $private=pd_profile($pdo,$privateUser,'private-'.$runId,'private');
    $hidden=pd_profile($pdo,$hiddenUser,'hidden-'.$runId,'public','hidden');
    $suspended=pd_profile($pdo,$suspendedUser,'suspended-'.$runId,'public','suspended');
    $inactive=pd_profile($pdo,$inactiveUser,'inactive-'.$runId);
    $blocked=pd_profile($pdo,$blockedUser,'blocked-'.$runId);
    pd_product($pdo,$alphaUser,$runId);
    $now=gmdate('Y-m-d H:i:s');
    mg_it_insert($pdo,'social_blocks',['blocking_user_id'=>$viewer,'blocked_user_id'=>$blockedUser,'created_at'=>$now]);
    mg_it_insert($pdo,'social_follows',['follower_user_id'=>$viewer,'followed_user_id'=>$alphaUser,'status'=>'active','created_at'=>$now,'updated_at'=>$now]);

    $anonymous=mg_profile_discovery_search($pdo,['q'=>$runId,'limit'=>20],null);
    $slugs=array_column($anonymous['items'],'slug');
    $result['public_and_unlisted']=in_array($alpha['slug'],$slugs,true)&&in_array($beta['slug'],$slugs,true)&&in_array($unlisted['slug'],$slugs,true);
    $result['visibility_exclusions']=!in_array($private['slug'],$slugs,true)&&!in_array($hidden['slug'],$slugs,true)&&!in_array($suspended['slug'],$slugs,true)&&!in_array($inactive['slug'],$slugs,true);
    $auth=mg_profile_discovery_search($pdo,['q'=>$runId,'limit'=>20],$viewer);
    $result['blocked_exclusion']=!in_array($blocked['slug'],array_column($auth['items'],'slug'),true);
    $ranked=mg_profile_discovery_search($pdo,['q'=>$alpha['slug'],'limit'=>10],null);
    $result['deterministic_ranking']=($ranked['items'][0]['slug']??null)===$alpha['slug'];
    $page1=mg_profile_discovery_search($pdo,['q'=>$runId,'limit'=>2],null);
    $page2=mg_profile_discovery_search($pdo,['q'=>$runId,'limit'=>2,'cursor'=>$page1['next_cursor']],null);
    $result['stable_cursor']=$page1['has_more']&&is_string($page1['next_cursor'])&&array_intersect(array_column($page1['items'],'id'),array_column($page2['items'],'id'))===[];
    $result['wildcard_safety']=mg_profile_discovery_search($pdo,['q'=>'%_'.$runId,'limit'=>20],null)['items']===[];
    $filtered=mg_profile_discovery_search($pdo,['type'=>'merchant','location'=>'Phoenix','category'=>'gift','limit'=>20],null);
    $result['filters']=count($filtered['items'])===1&&($filtered['items'][0]['slug']??null)===$alpha['slug'];
    $keys=[]; pd_keys($anonymous,$keys);
    $result['safe_projection']=array_intersect(['email','user_id','profile_id','metadata_json','moderation_note','provider_metadata'],$keys)===[];
    $full=mg_profile_discovery_read($pdo,['limit'=>10],null);
    $organic=array_unique(array_column($full['results']['items'],'result_kind'));
    $curated=array_unique(array_merge(array_column($full['sections']['featured'],'result_kind'),array_column($full['sections']['recent'],'result_kind'),array_column($full['sections']['storefronts'],'result_kind')));
    $result['curated_separation']=$organic===['organic']&&$curated===['curated']&&($full['policy']['private_behavioral_or_payment_data_used']??true)===false;
    foreach ($result as $name=>$passed) if ($name!=='rollback_clean') pd_assert($passed,$name);
    $pdo->rollBack(); $result['rollback_clean']=true;
    echo json_encode($result+['suite'=>'profile_discovery_search_foundation'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1);
}

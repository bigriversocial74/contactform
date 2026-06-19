<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

function mg_admin_mc_filters(array $input): array
{
    $domain=strtolower(mg_admin_mc_text($input['domain']??'all',24));
    if(!in_array($domain,['all','workspace','storefront','product','asset'],true))throw new MgAdminMerchantCatalogException('Invalid operations domain filter.',422);
    $status=strtolower(mg_admin_mc_text($input['status']??'',50));
    if($status!==''&&$status!=='attention'&&preg_match('/^[a-z0-9._-]+$/',$status)!==1)throw new MgAdminMerchantCatalogException('Invalid operations status filter.',422);
    return [
        'q'=>mb_strtolower(mg_admin_mc_text($input['q']??'',160)),
        'domain'=>$domain,'status'=>$status,
        'merchant_user_id'=>mg_admin_mc_user_id($input['merchant_user_id']??null),
        'date_from'=>mg_admin_mc_date($input['date_from']??null),'date_to'=>mg_admin_mc_date($input['date_to']??null),
        'limit'=>mg_admin_mc_limit($input['limit']??MG_ADMIN_MC_DEFAULT_LIMIT),'page'=>mg_admin_mc_page($input['page']??1),
    ];
}

function mg_admin_mc_subqueries(string $domain): array
{
    $queries=[
        'workspace'=><<<'SQL'
SELECT 'workspace' entity_type,mw.id entity_id,mw.public_id,mw.status,mw.eligibility_status secondary_status,mw.merchant_user_id,
COALESCE(u.display_name,u.full_name,u.email) merchant_name,u.email merchant_email,mw.display_name title,
CONCAT_WS(' · ',mw.business_type,CONCAT(mw.onboarding_percent,'% onboarded')) subtitle,mw.created_at,mw.updated_at,
IF(mw.status IN ('pending_review','suspended','archived') OR mw.eligibility_status IN ('ineligible','manual_review') OR mw.onboarding_percent<100,1,0) attention,
mw.onboarding_percent score,NULL asset_count,NULL issue_count
FROM merchant_workspaces mw INNER JOIN users u ON u.id=mw.merchant_user_id
SQL,
        'storefront'=><<<'SQL'
SELECT 'storefront' entity_type,s.id entity_id,s.public_id,s.status,mw.status secondary_status,s.merchant_user_id,
COALESCE(u.display_name,u.full_name,u.email) merchant_name,u.email merchant_email,s.display_name title,s.slug subtitle,s.created_at,s.updated_at,
IF(s.status<>'published' OR st.published_revision_id IS NULL,1,0) attention,
NULL score,(IF(s.logo_asset_id IS NULL,0,1)+IF(s.cover_asset_id IS NULL,0,1)) asset_count,
(IF(st.published_revision_id IS NULL,1,0)+IF(s.status='suspended',1,0)) issue_count
FROM merchant_storefronts s INNER JOIN users u ON u.id=s.merchant_user_id
LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=s.merchant_user_id
LEFT JOIN merchant_storefront_states st ON st.storefront_id=s.id
SQL,
        'product'=><<<'SQL'
SELECT 'product' entity_type,p.id entity_id,p.public_id,p.status,p.product_type secondary_status,p.merchant_user_id,
COALESCE(u.display_name,u.full_name,u.email) merchant_name,u.email merchant_email,COALESCE(v.title,p.slug) title,p.slug subtitle,p.created_at,p.updated_at,
IF(p.status<>'published' OR p.current_version_id IS NULL OR COALESCE(ax.unavailable_assets,0)>0,1,0) attention,
NULL score,COALESCE(ax.asset_count,0) asset_count,
(IF(p.current_version_id IS NULL,1,0)+COALESCE(ax.unavailable_assets,0)) issue_count
FROM catalog_products p INNER JOIN users u ON u.id=p.merchant_user_id
LEFT JOIN catalog_product_versions v ON v.id=p.current_version_id
LEFT JOIN (
 SELECT pva.product_version_id,COUNT(*) asset_count,SUM(CASE WHEN a.status<>'ready' THEN 1 ELSE 0 END) unavailable_assets
 FROM catalog_product_version_assets pva INNER JOIN catalog_assets a ON a.id=pva.asset_id GROUP BY pva.product_version_id
) ax ON ax.product_version_id=p.current_version_id
SQL,
        'asset'=><<<'SQL'
SELECT 'asset' entity_type,a.id entity_id,a.public_id,a.status,a.asset_type secondary_status,a.owner_user_id merchant_user_id,
COALESCE(u.display_name,u.full_name,u.email) merchant_name,u.email merchant_email,COALESCE(a.original_filename,a.storage_key) title,a.mime_type subtitle,a.created_at,a.updated_at,
IF(a.status<>'ready',1,0) attention,NULL score,1 asset_count,
IF(a.status<>'ready',1,0) issue_count
FROM catalog_assets a INNER JOIN users u ON u.id=a.owner_user_id
SQL,
    ];
    return $domain==='all'?array_values($queries):[$queries[$domain]];
}

function mg_admin_mc_summary(PDO $pdo): array
{
    $row=mg_admin_mc_one($pdo,<<<'SQL'
SELECT
(SELECT COUNT(*) FROM merchant_workspaces) workspaces_total,
(SELECT COUNT(*) FROM merchant_workspaces WHERE status IN ('pending_review','suspended') OR eligibility_status IN ('ineligible','manual_review')) workspaces_attention,
(SELECT COUNT(*) FROM merchant_storefronts WHERE status='published') storefronts_published,
(SELECT COUNT(*) FROM merchant_storefronts s LEFT JOIN merchant_storefront_states st ON st.storefront_id=s.id WHERE s.status<>'published' OR st.published_revision_id IS NULL) storefronts_attention,
(SELECT COUNT(*) FROM catalog_products WHERE status='published') products_published,
(SELECT COUNT(*) FROM catalog_products WHERE status IN ('draft','review','paused','archived') OR current_version_id IS NULL) products_attention,
(SELECT COUNT(*) FROM catalog_assets WHERE status='ready') assets_ready,
(SELECT COUNT(*) FROM catalog_assets WHERE status IN ('pending','quarantined','failed')) assets_attention,
(SELECT COUNT(*) FROM merchant_locations WHERE status='active') active_locations,
(SELECT COUNT(*) FROM merchant_team_members WHERE status='active') active_team_members
SQL)?:[];
    $result=[];foreach(['workspaces_total','workspaces_attention','storefronts_published','storefronts_attention','products_published','products_attention','assets_ready','assets_attention','active_locations','active_team_members'] as $key)$result[$key]=(int)($row[$key]??0);
    $result['generated_at']=gmdate('c');return $result;
}

function mg_admin_mc_list(PDO $pdo,array $input): array
{
    $f=mg_admin_mc_filters($input);$union=implode("\nUNION ALL\n",mg_admin_mc_subqueries($f['domain']));
    $sql='SELECT entity.* FROM ('.$union.') entity WHERE 1=1';$params=[];
    if($f['q']!==''){$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['q']).'%';$sql.=' AND LOWER(CONCAT_WS(" ",entity.public_id,entity.title,entity.subtitle,entity.merchant_name,entity.merchant_email)) LIKE ? ESCAPE "!"';$params[]=$needle;}
    if($f['status']==='attention')$sql.=' AND entity.attention=1';elseif($f['status']!==''){$sql.=' AND (LOWER(entity.status)=? OR LOWER(COALESCE(entity.secondary_status,""))=?)';$params[]=$f['status'];$params[]=$f['status'];}
    if($f['merchant_user_id']!==null){$sql.=' AND entity.merchant_user_id=?';$params[]=$f['merchant_user_id'];}
    if($f['date_from']!==null){$sql.=' AND entity.created_at>=?';$params[]=$f['date_from'].' 00:00:00';}
    if($f['date_to']!==null){$until=(new DateTimeImmutable($f['date_to'],new DateTimeZone('UTC')))->modify('+1 day');$sql.=' AND entity.created_at<?';$params[]=$until->format('Y-m-d 00:00:00');}
    $offset=($f['page']-1)*$f['limit'];$sql.=' ORDER BY entity.attention DESC,entity.updated_at DESC,entity.entity_type ASC,entity.entity_id DESC LIMIT '.($f['limit']+1).' OFFSET '.$offset;
    $rows=mg_admin_mc_all($pdo,$sql,$params);$hasMore=count($rows)>$f['limit'];if($hasMore)array_pop($rows);
    $items=array_map(static fn(array $row):array=>[
        'entity_type'=>(string)$row['entity_type'],'entity_id'=>(int)$row['entity_id'],'public_id'=>(string)$row['public_id'],
        'status'=>(string)$row['status'],'secondary_status'=>$row['secondary_status']!==null?(string)$row['secondary_status']:null,
        'merchant'=>['id'=>(int)$row['merchant_user_id'],'display_name'=>(string)$row['merchant_name'],'email'=>(string)$row['merchant_email']],
        'title'=>(string)$row['title'],'subtitle'=>$row['subtitle']!==null?(string)$row['subtitle']:null,
        'created_at'=>(string)$row['created_at'],'updated_at'=>(string)$row['updated_at'],'attention'=>(bool)$row['attention'],
        'score'=>$row['score']!==null?(int)$row['score']:null,'asset_count'=>$row['asset_count']!==null?(int)$row['asset_count']:null,'issue_count'=>$row['issue_count']!==null?(int)$row['issue_count']:null,
    ],$rows);
    return ['items'=>$items,'page'=>$f['page'],'limit'=>$f['limit'],'has_more'=>$hasMore,'next_page'=>$hasMore?$f['page']+1:null,'filters'=>$f,'summary'=>mg_admin_mc_summary($pdo)];
}

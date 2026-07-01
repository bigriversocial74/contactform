<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

function mg_acpm_ids(string $raw): array{
  $ids=[];foreach(explode(',',$raw) as $id){$id=trim($id);if($id!==''&&strlen($id)<=190)$ids[$id]=$id;if(count($ids)>=80)break;}return array_values($ids);
}
function mg_acpm_json($json): array{
  if($json===null||trim((string)$json)==='')return [];$data=json_decode((string)$json,true);return is_array($data)?$data:[];
}
function mg_acpm_url($value): string{
  $url=trim((string)$value);if($url===''||strlen($url)>800)return '';
  if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
  if(filter_var($url,FILTER_VALIDATE_URL)===false)return '';
  $parts=parse_url($url);return is_array($parts)&&in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)&&!empty($parts['host'])?$url:'';
}
function mg_acpm_kind(string $url,string $fallback='file'): string{
  $ext=strtolower(pathinfo((string)(parse_url($url,PHP_URL_PATH)?:$url),PATHINFO_EXTENSION));
  return match($ext){'jpg','jpeg','png','gif','webp','avif'=>'image','mp3','wav','m4a','aac','ogg'=>'audio','mp4','mov','webm'=>'video','pdf','zip'=>'download',default=>$fallback};
}
function mg_acpm_pack(array $metadata,string $source): array{
  $assets=[];$pack=is_array($metadata['media_pack']??null)?$metadata['media_pack']:(is_array($metadata['reward_media_pack']??null)?$metadata['reward_media_pack']:(is_array($metadata['media']??null)?$metadata['media']:[]));
  $cover=mg_acpm_url($pack['cover_image_url']??$metadata['cover_image_url']??$metadata['cover_url']??$metadata['image_url']??'');
  if($cover!=='')$assets[]=['role'=>'cover','asset_type'=>'image','mime_type'=>'','title'=>'Cover image','url'=>$cover,'source'=>$source,'sort_order'=>0];
  $items=is_array($pack['media_items']??null)?$pack['media_items']:(is_array($metadata['media_items']??null)?$metadata['media_items']:(is_array($metadata['assets']??null)?$metadata['assets']:[]));
  $sort=1;foreach($items as $item){if(!is_array($item))continue;$url=mg_acpm_url($item['url']??$item['href']??$item['asset_url']??'');if($url==='')continue;$type=strtolower(trim((string)($item['type']??$item['asset_type']??'')));if($type==='')$type=mg_acpm_kind($url,'download');$assets[]=['role'=>(string)($item['role']??($type==='image'?'gallery':$type)),'asset_type'=>$type,'mime_type'=>(string)($item['mime']??$item['mime_type']??''),'title'=>(string)($item['title']??$item['name']??ucfirst($type)),'url'=>$url,'source'=>$source,'sort_order'=>$sort++];}
  return $assets;
}
function mg_acpm_asset(array $asset): array{
  return ['role'=>(string)($asset['role']??'other'),'asset_type'=>(string)($asset['asset_type']??'other'),'mime_type'=>(string)($asset['mime_type']??''),'title'=>(string)($asset['original_filename']??ucfirst((string)($asset['role']??'media'))),'url'=>'/api/public/media.php?asset='.rawurlencode((string)$asset['asset_id']),'source'=>'catalog_product','sort_order'=>(int)($asset['sort_order']??0)];
}
function mg_acpm_cover(array $assets): string{
  foreach(['cover','thumbnail','inside_cover','gallery','carousel','back'] as $role){foreach($assets as $asset){$type=(string)($asset['asset_type']??'');$mime=(string)($asset['mime_type']??'');if((string)($asset['role']??'')===$role&&($type==='image'||str_starts_with($mime,'image/')))return (string)$asset['url'];}}
  foreach($assets as $asset){$type=(string)($asset['asset_type']??'');$mime=(string)($asset['mime_type']??'');if($type==='image'||str_starts_with($mime,'image/'))return (string)$asset['url'];}return '';
}
function mg_acpm_kind_assets(array $assets): string{
  foreach(['image','video','audio','download'] as $kind){foreach($assets as $asset){$type=(string)($asset['asset_type']??'');$mime=(string)($asset['mime_type']??'');if($type===$kind||str_starts_with($mime,$kind.'/'))return $kind;}}return $assets?'media':'none';
}

mg_require_method('GET');$user=mg_require_api_user();$ids=mg_acpm_ids((string)($_GET['ids']??$_GET['id']??''));if($ids===[])mg_ok(['items'=>[]]);
$pdo=mg_db();$ph=implode(',',array_fill(0,count($ids),'?'));
$stmt=$pdo->prepare("SELECT ac.public_id action_item_id,i.product_version_id,i.product_id,i.pppm_item_id,i.title_snapshot,i.description_snapshot,i.metadata_json instance_metadata_json,wi.public_id wallet_item_id,wi.metadata_json wallet_metadata_json,rt.public_id reward_template_id,rt.title reward_title,rt.reward_type,rt.description reward_description,rt.metadata_json reward_metadata_json,c.title campaign_title,c.metadata_json campaign_metadata_json FROM microgift_inbox_items ac INNER JOIN microgift_instances i ON i.id=ac.instance_id LEFT JOIN wallet_items wi ON wi.pppm_item_id=i.pppm_item_id LEFT JOIN reward_templates rt ON rt.id=wi.reward_template_id LEFT JOIN campaigns c ON c.id=wi.campaign_id WHERE ac.user_id=? AND ac.public_id IN ({$ph}) AND ac.archived_at IS NULL");
$stmt->execute(array_merge([(int)$user['id']],$ids));$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$rows)mg_ok(['items'=>[]]);
$versionIds=[];foreach($rows as $row){$vid=(int)($row['product_version_id']??0);if($vid>0)$versionIds[$vid]=$vid;}
$catalog=[];if($versionIds){$vph=implode(',',array_fill(0,count($versionIds),'?'));$assets=$pdo->prepare("SELECT cpva.product_version_id,cpva.role,cpva.sort_order,ca.public_id asset_id,ca.asset_type,ca.mime_type,ca.original_filename FROM catalog_product_version_assets cpva INNER JOIN catalog_assets ca ON ca.id=cpva.asset_id AND ca.status='ready' WHERE cpva.product_version_id IN ({$vph}) ORDER BY cpva.product_version_id,FIELD(cpva.role,'cover','thumbnail','inside_cover','gallery','carousel','audio','download','back','other'),cpva.sort_order,cpva.id");$assets->execute(array_values($versionIds));foreach($assets->fetchAll(PDO::FETCH_ASSOC) as $asset){$vid=(int)$asset['product_version_id'];unset($asset['product_version_id']);$catalog[$vid][]=mg_acpm_asset($asset);}}
$items=[];foreach($rows as $row){$assets=[];$vid=(int)($row['product_version_id']??0);if($vid>0&&!empty($catalog[$vid]))$assets=array_merge($assets,$catalog[$vid]);foreach(['reward_metadata_json'=>'reward_template','wallet_metadata_json'=>'wallet_item','instance_metadata_json'=>'microgift','campaign_metadata_json'=>'campaign'] as $field=>$source){$assets=array_merge($assets,mg_acpm_pack(mg_acpm_json($row[$field]??null),$source));}$items[(string)$row['action_item_id']]=['title'=>(string)($row['title_snapshot']??$row['reward_title']??'Gift'),'description'=>(string)($row['description_snapshot']??$row['reward_description']??''),'wallet_item_id'=>(string)($row['wallet_item_id']??''),'reward_template_id'=>(string)($row['reward_template_id']??''),'reward_type'=>(string)($row['reward_type']??''),'media_assets'=>$assets,'media_count'=>count($assets),'cover_url'=>mg_acpm_cover($assets),'primary_media_kind'=>mg_acpm_kind_assets($assets)];}
mg_ok(['items'=>$items]);

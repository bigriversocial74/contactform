<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
$pdo=mg_db();
$actionItemId=trim((string)($_GET['action_item_id']??$_GET['id']??''));
if($actionItemId==='')mg_fail('Action Center item id is required.',422);

$stmt=$pdo->prepare("SELECT ac.folder,ac.state,ac.user_id,i.id instance_internal_id,i.public_id instance_id,i.owner_user_id,i.status instance_status,
        v.product_id,cp.public_id product_id,cp.merchant_user_id
    FROM microgift_inbox_items ac
    INNER JOIN microgift_instances i ON i.id=ac.instance_id
    INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
    INNER JOIN catalog_products cp ON cp.id=v.product_id
    WHERE ac.public_id=? AND ac.user_id=? AND ac.archived_at IS NULL
    LIMIT 1");
$stmt->execute([$actionItemId,(int)$user['id']]);
$item=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$item)mg_fail('Action Center item not found.',404);
if((int)$item['owner_user_id']!==(int)$user['id'])mg_fail('You do not own this Microgift.',403);
if(!in_array((string)$item['instance_status'],['claimed','redeemable'],true))mg_fail('Microgift is not redeemable.',409);
if((int)$item['merchant_user_id']<1)mg_fail('Redeemable merchant catalog was not found.',409);

$locations=$pdo->prepare("SELECT ml.public_id location_id,ml.name,ml.address_line1,ml.address_line2,ml.city,ml.region,ml.postal_code,ml.country_code,ml.phone,ml.status,is_primary
    FROM merchant_workspaces mw
    INNER JOIN merchant_locations ml ON ml.workspace_id=mw.id
    WHERE mw.merchant_user_id=? AND ml.status='active'
    ORDER BY ml.is_primary DESC,ml.name");
$locations->execute([(int)$item['merchant_user_id']]);
mg_ok([
    'action_item_id'=>$actionItemId,
    'instance_id'=>(string)$item['instance_id'],
    'product_id'=>(string)$item['product_id'],
    'locations'=>$locations->fetchAll(PDO::FETCH_ASSOC),
]);

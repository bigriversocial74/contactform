<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/microgifts/_operations.php';
mg_require_method('GET');
$user=mg_require_permission('microgift.reviews.manage');
$id=trim((string)($_GET['id']??''));
if($id==='')mg_fail('Microgift instance ID is required.',422);
$pdo=mg_db();
$stmt=$pdo->prepare("SELECT i.*,t.public_id template_public_id,t.name template_name,v.public_id version_public_id,v.version_number,
    p.public_id pppm_public_id,p.status pppm_status,g.public_id legacy_gift_public_id
    FROM microgift_instances i
    INNER JOIN microgift_templates t ON t.id=i.template_id
    INNER JOIN microgift_template_versions v ON v.id=i.template_version_id
    LEFT JOIN pppm_items p ON p.id=i.pppm_item_id
    LEFT JOIN gifts g ON g.id=i.legacy_gift_id
    WHERE i.public_id=? LIMIT 1");
$stmt->execute([$id]);$instance=$stmt->fetch();
if(!$instance)mg_fail('Microgift instance not found.',404);
$events=$pdo->prepare('SELECT public_id,event_type,actor_user_id,source_type,source_reference,payload_json,created_at FROM microgift_events WHERE instance_id=? ORDER BY id ASC');$events->execute([(int)$instance['id']]);
$claims=$pdo->prepare('SELECT public_id,status,claimant_user_id,previous_owner_user_id,verified_at,completed_at,created_at FROM microgift_claims WHERE instance_id=? ORDER BY id ASC');$claims->execute([(int)$instance['id']]);
$redemptions=$pdo->prepare('SELECT public_id,status,claimant_user_id,merchant_user_id,location_reference,amount_cents,currency,redeemed_at FROM microgift_redemptions WHERE instance_id=? ORDER BY id ASC');$redemptions->execute([(int)$instance['id']]);
$lifecycle=$pdo->prepare('SELECT public_id,action_type,from_status,to_status,source_type,source_reference,reason,created_at FROM microgift_lifecycle_actions WHERE instance_id=? ORDER BY id ASC');$lifecycle->execute([(int)$instance['id']]);
$reviews=$pdo->prepare('SELECT public_id,review_type,status,priority,summary,resolution_note,created_at,updated_at FROM microgift_review_items WHERE instance_id=? ORDER BY id ASC');$reviews->execute([(int)$instance['id']]);
mg_ok(['instance'=>$instance,'events'=>$events->fetchAll(),'claims'=>$claims->fetchAll(),'redemptions'=>$redemptions->fetchAll(),'lifecycle'=>$lifecycle->fetchAll(),'reviews'=>$reviews->fetchAll()]);

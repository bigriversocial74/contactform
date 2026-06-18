<?php
declare(strict_types=1);
require_once __DIR__ . '/_distribution.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission($method==='GET'?'distribution.analytics.view':'distribution.programs.manage');
$pdo=mg_db();
if($method==='GET'){
 $programId=trim((string)($_GET['program_id']??''));
 $stmt=$pdo->prepare('SELECT dpp.id,cpt.public_id AS template_id,cpv.title,cpv.unit_value_cents,cpv.currency,dpp.weight,dpp.quantity_limit,dpp.quantity_issued,dpp.status FROM distribution_program_products dpp INNER JOIN distribution_programs dp ON dp.id=dpp.program_id INNER JOIN catalog_pppm_templates cpt ON cpt.id=dpp.pppm_template_id INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id WHERE dp.public_id=? AND dp.merchant_user_id=? ORDER BY dpp.id');
 $stmt->execute([$programId,(int)$user['id']]);mg_ok(['products'=>$stmt->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);
$program=mg_distribution_program_for_update($pdo,(int)$user['id'],trim((string)($input['program_id']??'')));
$templateId=trim((string)($input['template_id']??''));$weight=max(1,(int)($input['weight']??1));$limit=isset($input['quantity_limit'])&&$input['quantity_limit']!==''?max(1,(int)$input['quantity_limit']):null;$status=trim((string)($input['status']??'active'));
if(!in_array($status,['active','inactive','exhausted'],true))mg_fail('Invalid program product status.',422);
$stmt=$pdo->prepare("SELECT cpt.id FROM catalog_pppm_templates cpt INNER JOIN catalog_product_versions cpv ON cpv.id=cpt.product_version_id INNER JOIN catalog_products cp ON cp.id=cpv.product_id WHERE cpt.public_id=? AND cp.merchant_user_id=? AND cpt.status='active' LIMIT 1");$stmt->execute([$templateId,(int)$user['id']]);$templateDbId=$stmt->fetchColumn();if(!$templateDbId)mg_fail('PPPM template not found.',404);
$pdo->prepare("INSERT INTO distribution_program_products (program_id,pppm_template_id,weight,quantity_limit,status,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE weight=VALUES(weight),quantity_limit=VALUES(quantity_limit),status=VALUES(status)")->execute([(int)$program['id'],(int)$templateDbId,$weight,$limit,$status]);
mg_ok(['program_id'=>$program['public_id'],'template_id'=>$templateId,'status'=>$status],'Program product saved.',201);

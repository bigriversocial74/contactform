<?php
declare(strict_types=1);
require_once __DIR__ . '/_merchant.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission($method==='GET'?'merchant.intelligence.view':'merchant.reports.manage');
$pdo=mg_db();
if($method==='GET'){
 $reports=$pdo->prepare('SELECT public_id,name,report_type,date_range_key,filters_json,columns_json,status,created_at,updated_at FROM merchant_saved_reports WHERE merchant_user_id=? AND status<>\'archived\' ORDER BY updated_at DESC');$reports->execute([(int)$user['id']]);
 $schedules=$pdo->prepare('SELECT mrs.public_id,mrs.frequency,mrs.timezone,mrs.format,mrs.status,mrs.next_run_at,mrs.last_run_at,msr.public_id report_id,msr.name report_name FROM merchant_report_schedules mrs INNER JOIN merchant_saved_reports msr ON msr.id=mrs.saved_report_id WHERE mrs.merchant_user_id=? AND mrs.status<>\'archived\' ORDER BY mrs.updated_at DESC');$schedules->execute([(int)$user['id']]);
 $exports=$pdo->prepare('SELECT public_id,export_type,format,date_from,date_to,privacy_mode,status,row_count,expires_at,failure_message,created_at FROM intelligence_export_jobs WHERE merchant_user_id=? ORDER BY created_at DESC LIMIT 100');$exports->execute([(int)$user['id']]);
 mg_ok(['reports'=>$reports->fetchAll(),'schedules'=>$schedules->fetchAll(),'exports'=>$exports->fetchAll()]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);$action=trim((string)($input['action']??'save'));
if($action==='save'){
 $name=trim((string)($input['name']??''));$type=trim((string)($input['report_type']??'overview'));$range=trim((string)($input['date_range_key']??'last_30_days'));
 if($name===''||mb_strlen($name)>180||!in_array($type,['overview','campaigns','products','locations','pppm_funnel','engagement','forecast'],true))mg_fail('Invalid saved report.',422);
 $filters=is_array($input['filters']??null)?json_encode($input['filters'],JSON_UNESCAPED_SLASHES):null;$columns=is_array($input['columns']??null)?json_encode($input['columns'],JSON_UNESCAPED_SLASHES):null;$public=mg_merchant_uuid();
 $pdo->prepare("INSERT INTO merchant_saved_reports (public_id,merchant_user_id,name,report_type,date_range_key,filters_json,columns_json,status,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'active',?,NOW(),NOW())")->execute([$public,(int)$user['id'],$name,$type,$range,$filters,$columns,(int)$user['id']]);
 mg_audit('merchant.report_saved','merchant_saved_report',['report_id'=>$public,'report_type'=>$type],(int)$user['id']);mg_ok(['report_id'=>$public],'Report saved.',201);
}
if($action==='schedule'){
 $reportId=trim((string)($input['report_id']??''));$frequency=trim((string)($input['frequency']??'weekly'));$timezone=trim((string)($input['timezone']??'UTC'));$email=strtolower(trim((string)($input['recipient_email']??'')));$format=trim((string)($input['format']??'csv'));
 if($reportId===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($frequency,['daily','weekly','monthly'],true)||!in_array($format,['csv','json'],true))mg_fail('Invalid report schedule.',422);
 $stmt=$pdo->prepare('SELECT id FROM merchant_saved_reports WHERE public_id=? AND merchant_user_id=? AND status=\'active\' LIMIT 1');$stmt->execute([$reportId,(int)$user['id']]);$dbId=$stmt->fetchColumn();if(!$dbId)mg_fail('Saved report not found.',404);
 $secret=(string)(getenv('MG_REPORT_EMAIL_SECRET')?:getenv('MG_MERCHANT_INVITE_SECRET')?:'');if($secret==='')mg_fail('Report scheduling is not configured.',503);$hash=hash_hmac('sha256',$email,$secret);$public=mg_merchant_uuid();$next=date('Y-m-d H:i:s',strtotime('+1 '.$frequency));
 $pdo->prepare("INSERT INTO merchant_report_schedules (public_id,merchant_user_id,saved_report_id,frequency,timezone,recipient_email_hash,format,status,next_run_at,created_by_user_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'active',?,?,NOW(),NOW())")->execute([$public,(int)$user['id'],(int)$dbId,$frequency,$timezone,$hash,$format,$next,(int)$user['id']]);
 mg_audit('merchant.report_scheduled','merchant_report_schedule',['schedule_id'=>$public,'report_id'=>$reportId,'frequency'=>$frequency],(int)$user['id']);mg_ok(['schedule_id'=>$public,'next_run_at'=>$next],'Report schedule created.',201);
}
mg_fail('Invalid report action.',422);

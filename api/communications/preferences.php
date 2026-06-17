<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$user=mg_require_permission($method==='GET'?'notification.view':'notification.preferences.manage');
$pdo=mg_db();
$types=['gift','message','claim','delivery','distribution','campaign','merchant','security','system'];
if($method==='GET'){
 $stmt=$pdo->prepare('SELECT notification_type,in_app_enabled,email_enabled,sms_enabled,push_enabled,digest_mode,quiet_hours_start,quiet_hours_end,timezone FROM notification_preferences WHERE user_id=? ORDER BY notification_type');
 $stmt->execute([(int)$user['id']]);$saved=[];foreach($stmt->fetchAll() as $row)$saved[$row['notification_type']]=$row;
 $preferences=[];foreach($types as $type)$preferences[]=$saved[$type]??['notification_type'=>$type,'in_app_enabled'=>1,'email_enabled'=>1,'sms_enabled'=>0,'push_enabled'=>1,'digest_mode'=>'immediate','quiet_hours_start'=>null,'quiet_hours_end'=>null,'timezone'=>'UTC'];
 mg_ok(['preferences'=>$preferences]);
}
if($method!=='POST')mg_fail('Method not allowed.',405);
$input=mg_input();mg_require_csrf_for_write($input);$type=trim((string)($input['notification_type']??''));if(!in_array($type,$types,true))mg_fail('Invalid notification type.',422);$digest=trim((string)($input['digest_mode']??'immediate'));if(!in_array($digest,['immediate','hourly','daily','weekly','off'],true))mg_fail('Invalid digest mode.',422);$timezone=trim((string)($input['timezone']??'UTC'));if($timezone===''||mb_strlen($timezone)>64)mg_fail('Invalid timezone.',422);
$values=[(int)$user['id'],$type,!empty($input['in_app_enabled'])?1:0,!empty($input['email_enabled'])?1:0,!empty($input['sms_enabled'])?1:0,!empty($input['push_enabled'])?1:0,$digest,trim((string)($input['quiet_hours_start']??''))?:null,trim((string)($input['quiet_hours_end']??''))?:null,$timezone];
$pdo->prepare('INSERT INTO notification_preferences (user_id,notification_type,in_app_enabled,email_enabled,sms_enabled,push_enabled,digest_mode,quiet_hours_start,quiet_hours_end,timezone,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE in_app_enabled=VALUES(in_app_enabled),email_enabled=VALUES(email_enabled),sms_enabled=VALUES(sms_enabled),push_enabled=VALUES(push_enabled),digest_mode=VALUES(digest_mode),quiet_hours_start=VALUES(quiet_hours_start),quiet_hours_end=VALUES(quiet_hours_end),timezone=VALUES(timezone),updated_at=NOW()')->execute($values);
mg_audit('notification.preference_updated','notification_preference',['notification_type'=>$type,'digest_mode'=>$digest],(int)$user['id']);mg_ok(['notification_type'=>$type],'Notification preference saved.');

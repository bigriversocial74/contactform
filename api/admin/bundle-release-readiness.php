<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__,2).'/includes/admin-permission-matrix.php';
require_once dirname(__DIR__).'/bundles/_release_readiness.php';
$pdo=mg_db();$user=mg_authenticated_user();if(!$user||(int)($user['id']??0)<1)mg_fail('Sign in to continue.',401);if(!mg_admin_permission_user_has($user,'commerce.manage')&&!mg_admin_permission_user_has($user,'admin'))mg_fail('Admin commerce access is required.',403);
$actor=(int)$user['id'];$method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));$input=$method==='POST'?mg_input():[];$action=strtolower(trim((string)($input['action']??$_GET['action']??'dashboard')));
try{
 if($method==='GET'&&$action==='dashboard'){
  $environment=trim((string)($_GET['environment']??(mg_payment_mode()==='live'?'live':'test')));if(!in_array($environment,['test','live'],true))throw new InvalidArgumentException('Invalid environment.');
  $control=mg_bundle_release_control($pdo,$environment);$health=mg_bundle_release_checks($pdo,$environment);$snapshots=$pdo->prepare("SELECT * FROM gift_bundle_release_health_snapshots WHERE environment=? ORDER BY captured_at DESC LIMIT 50");$snapshots->execute([$environment]);
  $events=$pdo->prepare("SELECT e.*,u.display_name actor_name FROM gift_bundle_release_events e LEFT JOIN users u ON u.id=e.actor_user_id WHERE e.release_control_id=? ORDER BY e.created_at DESC LIMIT 100");$events->execute([(int)$control['id']]);
  mg_ok(['control'=>$control,'health'=>$health,'snapshots'=>$snapshots->fetchAll(PDO::FETCH_ASSOC),'events'=>$events->fetchAll(PDO::FETCH_ASSOC)]);
 }
 if($method==='POST'&&$action==='update_control'){
  mg_require_csrf_for_write($input);if(trim((string)($input['confirmation']??''))!=='ACTIVATE')mg_fail('Type ACTIVATE to confirm release changes.',422);
  $environment=trim((string)($input['environment']??''));$stage=trim((string)($input['rollout_stage']??''));$percent=(int)($input['traffic_percent']??0);$reason=trim((string)($input['reason']??''));
  if(!in_array($environment,['test','live'],true)||!in_array($stage,['disabled','internal','pilot','limited','general'],true)||$percent<0||$percent>100||$reason==='')throw new InvalidArgumentException('Invalid release control request.');
  $health=mg_bundle_release_checks($pdo,$environment);$enableTransfers=!empty($input['transfers_enabled']);$enableReversals=!empty($input['reversals_enabled']);$stop=!empty($input['emergency_stop']);
  if(($enableTransfers||$enableReversals||$stage!=='disabled'||$percent>0)&&$health['status']!=='healthy')throw new InvalidArgumentException('Release activation requires a healthy readiness score.');
  $pdo->beginTransaction();$control=mg_bundle_release_control($pdo,$environment);$before=json_encode($control,JSON_THROW_ON_ERROR);
  $pdo->prepare("UPDATE gift_bundle_release_controls SET rollout_stage=?,traffic_percent=?,transfers_enabled=?,reversals_enabled=?,emergency_stop=?,approved_by_user_id=?,approval_note=?,approved_at=NOW(),updated_by_user_id=?,updated_at=NOW() WHERE id=?")->execute([$stage,$percent,$enableTransfers?1:0,$enableReversals?1:0,$stop?1:0,$actor,$reason,$actor,(int)$control['id']]);
  $after=mg_bundle_release_control($pdo,$environment);$key=trim((string)($input['idempotency_key']??''));if($key==='')throw new InvalidArgumentException('Idempotency key is required.');
  $pdo->prepare("INSERT INTO gift_bundle_release_events (public_id,release_control_id,actor_user_id,event_type,previous_state_json,next_state_json,reason,idempotency_key,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")->execute([mg_public_uuid(),(int)$control['id'],$actor,$stop?'emergency_stop_updated':'rollout_updated',$before,json_encode($after,JSON_THROW_ON_ERROR),$reason,$key]);$pdo->commit();mg_ok(['control'=>$after,'health'=>$health]);
 }
 mg_fail('Unsupported operation.',405);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail($e->getMessage(),422);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();mg_fail_unexpected($e,'bundle.release.readiness.failure','Unable to process release readiness.',500,[],$actor);}

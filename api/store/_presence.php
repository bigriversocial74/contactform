<?php
/**
 * Merchant location presence policy.
 *
 * A merchant keeps a personal user persona and may separately operate a business
 * persona for a registered merchant location. Presence and entry policy are
 * location-scoped; the merchant's browser/user coordinates are never used as the
 * business location source of truth.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_presence_table(PDO $pdo,string $table): bool {
    if(preg_match('/^[A-Za-z0-9_]+$/',$table)!==1)return false;
    static $cache=[];$key=spl_object_id($pdo).':'.$table;if(array_key_exists($key,$cache))return $cache[$key];
    try{$db=(string)($pdo->query('SELECT DATABASE()')->fetchColumn()?:'');$s=$pdo->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1');$s->execute([$db,$table]);return $cache[$key]=(bool)$s->fetchColumn();}catch(Throwable){return $cache[$key]=false;}
}
function mg_presence_column(PDO $pdo,string $table,string $column): bool {
    if(!mg_presence_table($pdo,$table)||preg_match('/^[A-Za-z0-9_]+$/',$column)!==1)return false;
    static $cache=[];$key=spl_object_id($pdo).':'.$table.':'.$column;if(array_key_exists($key,$cache))return $cache[$key];
    try{$db=(string)($pdo->query('SELECT DATABASE()')->fetchColumn()?:'');$s=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1');$s->execute([$db,$table,$column]);return $cache[$key]=(bool)$s->fetchColumn();}catch(Throwable){return $cache[$key]=false;}
}
function mg_presence_ready(PDO $pdo): bool {
    return mg_presence_table($pdo,'merchant_locations')
        && mg_presence_column($pdo,'merchant_locations','world_presence_mode')
        && mg_presence_column($pdo,'merchant_locations','world_presence_status')
        && mg_presence_table($pdo,'world_canvas_persona_state')
        && mg_presence_table($pdo,'mg_store_presence_watchers');
}
function mg_presence_mode(mixed $value): string { $v=strtolower(trim((string)$value));return in_array($v,['allow_unattended','temporarily_closed'],true)?$v:'allow_unattended'; }
function mg_presence_status(mixed $value): string { return strtolower(trim((string)$value))==='world'?'world':'in_store'; }
function mg_presence_text(mixed $value,int $max=500): string { $v=preg_replace('/\s+/u',' ',trim((string)$value))??'';return mb_substr($v,0,$max); }
function mg_presence_location_fields(PDO $pdo): string {
    $optional=[
        'world_presence_mode'=>"'allow_unattended'",
        'world_presence_status'=>"'in_store'",
        'world_presence_cycle'=>'0',
        'world_presence_message'=>'NULL',
        'world_return_message'=>'NULL',
        'world_presence_updated_at'=>'NULL',
        'world_zone_radius_meters'=>'250',
        'geo_accuracy_meters'=>'NULL',
        'geo_source'=>"'merchant_locations'",
    ];
    $parts=['ml.id','ml.public_id','ml.name','ml.location_code','ml.address_line1','ml.city','ml.region','ml.postal_code','ml.country_code','ml.is_primary','ml.latitude','ml.longitude','ml.status'];
    foreach($optional as $column=>$fallback)$parts[]=mg_presence_column($pdo,'merchant_locations',$column)?'ml.'.$column:$fallback.' AS '.$column;
    return implode(', ',$parts);
}
function mg_presence_locations(PDO $pdo,int $merchantUserId,bool $lock=false): array {
    if($merchantUserId<1||!mg_presence_table($pdo,'merchant_locations'))return [];$fields=mg_presence_location_fields($pdo);$tail=$lock?' FOR UPDATE':'';
    try{
        if(mg_presence_column($pdo,'merchant_locations','merchant_user_id')){$s=$pdo->prepare("SELECT {$fields} FROM merchant_locations ml WHERE ml.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC,ml.name,ml.id{$tail}");$s->execute([$merchantUserId]);}
        elseif(mg_presence_column($pdo,'merchant_locations','workspace_id')&&mg_presence_table($pdo,'merchant_workspaces')){$s=$pdo->prepare("SELECT {$fields} FROM merchant_locations ml JOIN merchant_workspaces mw ON mw.id=ml.workspace_id WHERE mw.merchant_user_id=? AND ml.status='active' ORDER BY ml.is_primary DESC,ml.name,ml.id{$tail}");$s->execute([$merchantUserId]);}
        else return [];
        return $s->fetchAll(PDO::FETCH_ASSOC)?:[];
    }catch(Throwable){return [];}
}
function mg_presence_location(PDO $pdo,int $merchantUserId,int|string|null $locationId=null,bool $lock=false): ?array {
    $rows=mg_presence_locations($pdo,$merchantUserId,$lock);if(!$rows)return null;$needle=trim((string)$locationId);
    if($needle!=='')foreach($rows as $row)if((string)$row['id']===$needle||(string)$row['public_id']===$needle)return $row;
    return $rows[0];
}
function mg_presence_project(array $row): array {
    $mode=mg_presence_mode($row['world_presence_mode']??'');$status=mg_presence_status($row['world_presence_status']??'');
    return [
        'id'=>(string)($row['public_id']??''),'database_id'=>(int)($row['id']??0),'name'=>(string)($row['name']??'Merchant location'),'location_code'=>(string)($row['location_code']??''),
        'address_line1'=>(string)($row['address_line1']??''),'city'=>(string)($row['city']??''),'region'=>(string)($row['region']??''),'postal_code'=>(string)($row['postal_code']??''),'country_code'=>(string)($row['country_code']??''),
        'is_primary'=>(int)($row['is_primary']??0),'latitude'=>$row['latitude']===null?null:(float)$row['latitude'],'longitude'=>$row['longitude']===null?null:(float)$row['longitude'],
        'world_zone_radius_meters'=>(int)($row['world_zone_radius_meters']??250),'presence_mode'=>$mode,'presence_status'=>$status,'presence_cycle'=>(int)($row['world_presence_cycle']??0),
        'away_message'=>(string)($row['world_presence_message']??''),'return_message'=>(string)($row['world_return_message']??''),'presence_updated_at'=>(string)($row['world_presence_updated_at']??''),
        'entry_allowed'=>$status!=='world'||$mode==='allow_unattended',
    ];
}
function mg_presence_default_away(array $location): string {
    $name=trim((string)($location['name']??'This merchant'))?:'This merchant';
    return mg_presence_mode($location['world_presence_mode']??'')==='temporarily_closed'
        ?$name.' is currently out in the World Canvas, so this location is temporarily closed. We will message you when the merchant returns.'
        :$name.' is currently out in the World Canvas. The shop remains open, so you can browse and shop without the merchant present.';
}
function mg_presence_default_return(array $location): string { $name=trim((string)($location['name']??'The merchant'))?:'The merchant';return $name.' has returned to Store Canvas and is available again.'; }
function mg_presence_thread(PDO $pdo,int $merchantId,int $customerId,string $merchantLabel): array {
    $key='store_canvas:'.$merchantId.':'.$customerId;
    $s=$pdo->prepare('SELECT id,public_id FROM message_threads WHERE conversation_key=? ORDER BY id LIMIT 1 FOR UPDATE');$s->execute([$key]);$thread=$s->fetch(PDO::FETCH_ASSOC);
    if(!$thread){$public=mg_public_uuid();$pdo->prepare('INSERT INTO message_threads (public_id,gift_id,pppm_item_id,microgift_instance_id,conversation_key,created_by_user_id,subject,created_at,updated_at) VALUES (?,NULL,NULL,NULL,?,?,?,NOW(),NOW())')->execute([$public,$key,$merchantId,'Store Canvas: '.mb_substr($merchantLabel,0,140)]);$thread=['id'=>(int)$pdo->lastInsertId(),'public_id'=>$public];}
    $p=$pdo->prepare('INSERT IGNORE INTO message_thread_participants (thread_id,user_id,joined_at) VALUES (?,?,NOW())');$p->execute([(int)$thread['id'],$merchantId]);$p->execute([(int)$thread['id'],$customerId]);return $thread;
}
function mg_presence_send(PDO $pdo,int $merchantId,int $customerId,string $body,string $idempotency,array $location,string $event): array {
    if($customerId<1||$merchantId<1||$customerId===$merchantId)return ['message_id'=>null,'notification_id'=>null];
    $body=mg_presence_text($body);if($body==='')return ['message_id'=>null,'notification_id'=>null];
    if(!mg_presence_table($pdo,'message_threads')||!mg_presence_table($pdo,'messages'))return ['message_id'=>null,'notification_id'=>null];
    $label='Merchant';try{$s=$pdo->prepare('SELECT display_name FROM public_profiles WHERE user_id=? LIMIT 1');$s->execute([$merchantId]);$label=trim((string)($s->fetchColumn()?:'Merchant'))?:'Merchant';}catch(Throwable){}
    $thread=mg_presence_thread($pdo,$merchantId,$customerId,$label);$messageId=mg_public_uuid();$key=mb_substr($idempotency,0,190);
    try{$pdo->prepare('INSERT INTO messages (public_id,thread_id,sender_user_id,recipient_user_id,body,idempotency_key,source_type,source_reference,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')->execute([$messageId,(int)$thread['id'],$merchantId,$customerId,$body,$key,'store_presence','merchant_location:'.(string)($location['public_id']??'')]);}
    catch(PDOException $e){if((string)$e->getCode()==='23000')return ['message_id'=>null,'notification_id'=>null];throw $e;}
    $pdo->prepare('UPDATE message_threads SET updated_at=NOW() WHERE id=?')->execute([(int)$thread['id']]);$notification='';
    try{$notification=mg_create_notification($pdo,$customerId,'message',$event==='merchant_returned'?'Merchant returned':'Store update from '.$label,mb_substr($body,0,240),'/messages.php?thread='.rawurlencode((string)$thread['public_id']),['actor_user_id'=>$merchantId,'event_key'=>$event.':'.$messageId,'message_id'=>$messageId,'thread_public_id'=>(string)$thread['public_id'],'merchant_location_id'=>(int)($location['id']??0),'source_system'=>'store_presence']);}catch(Throwable){}
    return ['message_id'=>$messageId,'notification_id'=>$notification?:null,'thread_id'=>(string)$thread['public_id']];
}
function mg_presence_watch(PDO $pdo,array $location,int $merchantId,int $customerId,string $reason,?string $postId=null,?int $sessionId=null): array {
    if(!mg_presence_table($pdo,'mg_store_presence_watchers'))return [];$cycle=(int)($location['world_presence_cycle']??0);$public=mg_public_uuid();
    $pdo->prepare('INSERT INTO mg_store_presence_watchers (public_id,merchant_location_id,merchant_user_id,customer_user_id,presence_cycle,reason,source_post_public_id,source_session_id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE reason=VALUES(reason),source_post_public_id=COALESCE(VALUES(source_post_public_id),source_post_public_id),source_session_id=COALESCE(VALUES(source_session_id),source_session_id),updated_at=NOW()')->execute([$public,(int)$location['id'],$merchantId,$customerId,$cycle,mb_substr($reason,0,32),$postId,$sessionId]);
    $away=mg_presence_text($location['world_presence_message']??'');if($away==='')$away=mg_presence_default_away($location);$sent=mg_presence_send($pdo,$merchantId,$customerId,$away,'presence:away:'.(int)$location['id'].':'.$cycle.':'.$customerId,$location,'merchant_away');
    if(!empty($sent['message_id']))$pdo->prepare('UPDATE mg_store_presence_watchers SET away_message_id=?,away_message_sent_at=NOW(),updated_at=NOW() WHERE merchant_location_id=? AND customer_user_id=? AND presence_cycle=?')->execute([$sent['message_id'],(int)$location['id'],$customerId,$cycle]);return $sent;
}
function mg_presence_notify_return(PDO $pdo,array $location,int $merchantId): int {
    if(!mg_presence_table($pdo,'mg_store_presence_watchers'))return 0;$cycle=(int)($location['world_presence_cycle']??0);$s=$pdo->prepare('SELECT id,customer_user_id FROM mg_store_presence_watchers WHERE merchant_location_id=? AND presence_cycle=? AND return_notified_at IS NULL FOR UPDATE');$s->execute([(int)$location['id'],$cycle]);$count=0;
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $watch){$customer=(int)$watch['customer_user_id'];$body=mg_presence_text($location['world_return_message']??'');if($body==='')$body=mg_presence_default_return($location);$sent=mg_presence_send($pdo,$merchantId,$customer,$body,'presence:return:'.(int)$location['id'].':'.$cycle.':'.$customer,$location,'merchant_returned');$pdo->prepare('UPDATE mg_store_presence_watchers SET return_message_id=?,return_notified_at=NOW(),updated_at=NOW() WHERE id=?')->execute([$sent['message_id']??null,(int)$watch['id']]);$count++;}return $count;
}
function mg_presence_transition(PDO $pdo,int $merchantId,int|string|null $locationId,string $next,int $actorId): array {
    if(!mg_presence_ready($pdo))return ['schema_ready'=>false,'location'=>null,'return_notifications'=>0,'affected_customers'=>0];$next=mg_presence_status($next);$own=!$pdo->inTransaction();if($own)$pdo->beginTransaction();
    try{$location=mg_presence_location($pdo,$merchantId,$locationId,true);if(!$location)throw new RuntimeException('A registered merchant location is required.');$previous=mg_presence_status($location['world_presence_status']??'');$cycle=(int)($location['world_presence_cycle']??0);if($next==='world'&&$previous!=='world')$cycle++;
        $pdo->prepare('UPDATE merchant_locations SET world_presence_status=?,world_presence_cycle=?,world_presence_updated_at=NOW(),world_presence_actor_user_id=?,updated_at=NOW() WHERE id=?')->execute([$next,$cycle,$actorId?:null,(int)$location['id']]);$location['world_presence_status']=$next;$location['world_presence_cycle']=$cycle;$affected=0;$returns=0;
        if($next==='world'&&$previous!=='world'&&mg_presence_table($pdo,'mg_store_sessions')){$where=mg_presence_column($pdo,'mg_store_sessions','merchant_location_id')?' AND (merchant_location_id=? OR merchant_location_id IS NULL)':'';$params=[$merchantId];if($where)$params[]=(int)$location['id'];$s=$pdo->prepare("SELECT * FROM mg_store_sessions WHERE merchant_user_id=?{$where} AND active_key IS NOT NULL AND status IN ('entered','active','idle') AND exited_at IS NULL FOR UPDATE");$s->execute($params);foreach($s->fetchAll(PDO::FETCH_ASSOC) as $session){$affected++;mg_presence_watch($pdo,$location,$merchantId,(int)$session['customer_user_id'],'inside_when_merchant_left',null,(int)$session['id']);if(mg_presence_mode($location['world_presence_mode']??'')==='temporarily_closed'&&function_exists('mg_store_close_session_row'))mg_store_close_session_row($pdo,$session,'blocked');}}
        if($next==='in_store'&&$previous!=='in_store')$returns=mg_presence_notify_return($pdo,$location,$merchantId);if($own)$pdo->commit();return ['schema_ready'=>true,'location'=>mg_presence_project($location),'previous_status'=>$previous,'return_notifications'=>$returns,'affected_customers'=>$affected];
    }catch(Throwable $e){if($own&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function mg_presence_entry_status(PDO $pdo,int $merchantId): array {
    $location=mg_presence_location($pdo,$merchantId);if(!$location||!mg_presence_ready($pdo))return ['schema_ready'=>false,'allowed'=>true,'merchant_away'=>false,'mode'=>'allow_unattended','notice'=>null,'location'=>$location];$away=mg_presence_status($location['world_presence_status']??'')==='world';$mode=mg_presence_mode($location['world_presence_mode']??'');$notice=$away?(mg_presence_text($location['world_presence_message']??'')?:mg_presence_default_away($location)):null;return ['schema_ready'=>true,'allowed'=>!$away||$mode==='allow_unattended','merchant_away'=>$away,'mode'=>$mode,'notice'=>$notice,'location'=>$location,'projected_location'=>mg_presence_project($location)];
}
function mg_presence_handle_entry(PDO $pdo,int $merchantId,int $customerId,?string $postId=null): array {
    $state=mg_presence_entry_status($pdo,$merchantId);if(!empty($state['merchant_away'])&&is_array($state['location']??null)){$state['message']=mg_presence_watch($pdo,$state['location'],$merchantId,$customerId,!empty($state['allowed'])?'entered_unattended':'blocked_closed',$postId,null);}return $state;
}
function mg_presence_persona_state(PDO $pdo,int $userId): ?array {if($userId<1||!mg_presence_table($pdo,'world_canvas_persona_state'))return null;$s=$pdo->prepare('SELECT * FROM world_canvas_persona_state WHERE user_id=? LIMIT 1');$s->execute([$userId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;}
function mg_presence_save_persona(PDO $pdo,int $userId,string $kind,?int $locationId,string $surface,array $metadata=[]): array {
    if(!mg_presence_table($pdo,'world_canvas_persona_state'))return ['schema_ready'=>false];$kind=$kind==='merchant'?'merchant':'user';$surface=in_array($surface,['world_canvas','store_canvas'],true)?$surface:'world_canvas';$public=mg_public_uuid();$json=$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null;
    $pdo->prepare('INSERT INTO world_canvas_persona_state (public_id,user_id,persona_kind,merchant_location_id,active_surface,last_heartbeat_at,metadata_json,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),?,NOW(),NOW()) ON DUPLICATE KEY UPDATE persona_kind=VALUES(persona_kind),merchant_location_id=VALUES(merchant_location_id),active_surface=VALUES(active_surface),last_heartbeat_at=NOW(),metadata_json=VALUES(metadata_json),updated_at=NOW()')->execute([$public,$userId,$kind,$locationId,$surface,$json]);return mg_presence_persona_state($pdo,$userId)??['schema_ready'=>true,'persona_kind'=>$kind,'merchant_location_id'=>$locationId,'active_surface'=>$surface];
}

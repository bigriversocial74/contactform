<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once __DIR__ . '/_presence.php';

mg_require_method('GET');
$pdo=mg_db();
$user=mg_refresh_session_user();
$viewerId=$user?(int)$user['id']:null;
$postId=trim((string)($_GET['post_id']??''));

try {
    $schemaReady=mg_store_runtime_schema_ready($pdo);
    $data=['authenticated'=>$viewerId!==null,'schema_ready'=>$schemaReady,'active_session'=>null,'post_state'=>null];
    if($viewerId!==null&&$schemaReady)$data['active_session']=mg_store_runtime_project_session(mg_store_runtime_active_session_for_customer($pdo,$viewerId));
    if($postId!==''){
        $state=mg_store_runtime_feed_status_for_post($pdo,$viewerId,$postId);
        $target=mg_store_load_post_target($pdo,$postId);
        $presence=mg_presence_entry_status($pdo,(int)$target['merchant_user_id']);
        $state['merchant_presence']=[
            'away'=>!empty($presence['merchant_away']),
            'mode'=>(string)($presence['mode']??'allow_unattended'),
            'notice'=>$presence['notice']??null,
            'location'=>$presence['projected_location']??null,
        ];
        if(!empty($presence['merchant_away'])){
            $state['notice']=$presence['notice']??$state['notice'];
            if(empty($presence['allowed'])){
                $state['state']='temporarily_closed';
                $state['label']='Temporarily Closed';
            } elseif(($state['state']??'none')==='none'){
                $state['label']='Enter Unattended Store';
            }
        }
        $data['post_state']=$state;
    }
    mg_ok($data);
} catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);
} catch(RuntimeException $error){mg_fail($error->getMessage(),404);
} catch(Throwable $error){mg_security_log('error','store_canvas.status_failed','Store session status failed.',['exception_class'=>$error::class,'message'=>$error->getMessage()],$viewerId);mg_fail('Unable to load store session status.',500);}

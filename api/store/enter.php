<?php
declare(strict_types=1);

require_once __DIR__ . '/_canvas_runtime.php';
require_once __DIR__ . '/_presence.php';

mg_require_method('POST');
$input=mg_input();
mg_require_csrf_for_write($input);
$user=mg_require_api_user();
$pdo=mg_db();

try {
    $postId=mg_store_safe_public_id($input['post_id']??'','Post');
    $switchStore=!empty($input['switch_store']);
    mg_rate_limit('store.entry','user:'.(int)$user['id'],60,60);

    $target=mg_store_load_post_target($pdo,$postId);
    $presence=mg_presence_handle_entry($pdo,(int)$target['merchant_user_id'],(int)$user['id'],$postId);
    if(empty($presence['allowed'])){
        throw new RuntimeException((string)($presence['notice']??'This merchant location is temporarily closed.'));
    }

    $result=mg_store_runtime_enter_post($pdo,(int)$user['id'],$postId,$switchStore);
    $locationId=(int)($presence['location']['id']??0);
    $sessionPublicId=(string)($result['session']['id']??'');
    if($locationId>0&&$sessionPublicId!==''&&mg_presence_column($pdo,'mg_store_sessions','merchant_location_id')){
        $pdo->prepare('UPDATE mg_store_sessions SET merchant_location_id=?,updated_at=NOW() WHERE public_id=? AND customer_user_id=?')->execute([$locationId,$sessionPublicId,(int)$user['id']]);
    }
    $result['merchant_presence']=[
        'away'=>!empty($presence['merchant_away']),
        'mode'=>(string)($presence['mode']??'allow_unattended'),
        'notice'=>$presence['notice']??null,
        'location'=>$presence['projected_location']??null,
    ];
    $message=!empty($result['requires_confirmation'])?'Store switch confirmation required.':(!empty($presence['merchant_away'])?'Entered store while merchant is in World Canvas.':'Entered merchant store.');
    mg_ok($result,$message);
} catch(InvalidArgumentException $error){mg_fail($error->getMessage(),422);
} catch(RuntimeException $error){mg_fail($error->getMessage(),400);
} catch(Throwable $error){mg_security_log('error','store_canvas.entry_failed','Store canvas entry failed.',['exception_class'=>$error::class,'message'=>$error->getMessage()],(int)$user['id']);mg_fail('Unable to enter merchant store.',500);}

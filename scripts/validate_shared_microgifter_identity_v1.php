<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$files=[
'database/shared_microgifter_identity_v1.sql',
'api/public/v1/identity-authorize-start.php',
'identity-authorize.php',
'api/public/v1/identity-authorize-complete.php',
'api/public/v1/identity-token.php',
'api/public/v1/identity-link.php',
'.github/workflows/shared-microgifter-identity-validation.yml',
];
$read=static fn(string $p):string=>is_file($root.'/'.$p)?(string)file_get_contents($root.'/'.$p):'';
$checks=[];foreach($files as $f)$checks[]=['name'=>'file:'.$f,'ok'=>is_file($root.'/'.$f)];
$sql=$read('database/shared_microgifter_identity_v1.sql');$start=$read('api/public/v1/identity-authorize-start.php');$page=$read('identity-authorize.php');$complete=$read('api/public/v1/identity-authorize-complete.php');$token=$read('api/public/v1/identity-token.php');$link=$read('api/public/v1/identity-link.php');
$checks[]=['name'=>'authorization and link schema','ok'=>str_contains($sql,'developer_identity_authorizations')&&str_contains($sql,'developer_identity_links')&&str_contains($sql,'authorization_code_hash')&&str_contains($sql,"ENUM('participant','merchant')")];
$checks[]=['name'=>'allowed origin enforcement','ok'=>str_contains($start,'allowed_origins_json')&&str_contains($start,'Return URL origin is not allowed')];
$checks[]=['name'=>'short-lived authorization','ok'=>str_contains($start,'time() + 900')&&str_contains($complete,"status='approved'")];
$checks[]=['name'=>'continue and create account UX','ok'=>str_contains($page,'Continue with Microgifter')&&str_contains($page,'Create Microgifter account')&&str_contains($page,'Sign in with Microgifter')];
$checks[]=['name'=>'csrf and authenticated approval','ok'=>str_contains($complete,'mg_require_api_user')&&str_contains($complete,'mg_require_csrf_for_write')];
$checks[]=['name'=>'merchant role gate','ok'=>str_contains($complete,'A Microgifter merchant account is required.')&&str_contains($complete,"\$requestedRole==='merchant'")];
$checks[]=['name'=>'one-time hashed code','ok'=>str_contains($complete,"hash('sha256',\$code)")&&str_contains($token,'authorization_code_hash=NULL')&&str_contains($token,"status='exchanged'")];
$checks[]=['name'=>'transactional exchange','ok'=>str_contains($token,'beginTransaction')&&str_contains($token,'FOR UPDATE')&&str_contains($token,'commit()')&&str_contains($token,'rollBack()')];
$checks[]=['name'=>'link conflict prevention','ok'=>str_contains($sql,'uq_developer_identity_links_external')&&str_contains($token,'ON DUPLICATE KEY UPDATE')];
$checks[]=['name'=>'unlink and relink lifecycle','ok'=>str_contains($link,"status='revoked'")&&str_contains($token,"status='active'")&&str_contains($token,'revoked_at=NULL')];
$checks[]=['name'=>'audit and API logs','ok'=>str_contains($complete,'developer_identity_authorized')&&str_contains($token,'identity_token_exchanged')&&str_contains($link,'identity_link_revoked')];
$failed=array_values(array_filter($checks,static fn(array $c):bool=>!$c['ok']));
$score=max(0,10-count($failed)*0.4);echo json_encode(['ok'=>$failed===[],'score'=>number_format($score,1).'/10','checks'=>$checks,'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;exit($failed===[]?0:1);

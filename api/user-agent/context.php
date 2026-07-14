<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
mg_require_method('GET');
$user=mg_require_api_user();
$type=(string)($_GET['type']??'none');
$id=(string)($_GET['id']??'');
mg_user_agent_api_run(static fn():array=>['context'=>mg_personal_agent_public_context(mg_personal_agent_resolve_context(mg_db(),(int)$user['id'],$type,$id))]);

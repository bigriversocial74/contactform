<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/microgifts/_stage10e_operations.php';
if(PHP_SAPI!=='cli')exit(1);
$pdo=mg_db();$limit=max(1,min((int)($argv[1]??50),200));$rows=mg_outbox_claim_batch($pdo,$limit);$delivered=0;$failed=0;
foreach($rows as $row){
    try{
        $payload=json_decode((string)$row['payload_json'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($payload))throw new RuntimeException('Invalid outbox payload.');
        mg_event((string)$row['topic'],(string)$row['aggregate_type'],(string)$row['aggregate_public_id'],$payload,null);
        mg_outbox_complete($pdo,(int)$row['id'],true);$delivered++;
    }catch(Throwable $e){mg_outbox_complete($pdo,(int)$row['id'],false,$e->getMessage());$failed++;}
}
echo json_encode(['claimed'=>count($rows),'delivered'=>$delivered,'failed'=>$failed],JSON_THROW_ON_ERROR).PHP_EOL;

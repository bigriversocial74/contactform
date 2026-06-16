<?php
declare(strict_types=1);

function mg_it_assert(bool $condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}

function mg_it_scalar(PDO $pdo,string $sql,array $params=[]): mixed
{
    $stmt=$pdo->prepare($sql);$stmt->execute($params);return $stmt->fetchColumn();
}

function mg_it_insert(PDO $pdo,string $table,array $values): int
{
    $names=array_keys($values);
    $quoted=array_map(static fn(string $name):string=>'`'.$name.'`',$names);
    $pdo->prepare('INSERT INTO `'.$table.'` ('.implode(',',$quoted).') VALUES ('.implode(',',array_fill(0,count($names),'?')).')')
        ->execute(array_values($values));
    return (int)$pdo->lastInsertId();
}

function mg_it_user(PDO $pdo,string $email,string $name): int
{
    return mg_it_insert($pdo,'users',[
        'email'=>$email,
        'password_hash'=>password_hash('BehaviorPassword123!',PASSWORD_DEFAULT),
        'full_name'=>$name,
        'display_name'=>$name,
        'status'=>'active',
        'email_verified_at'=>gmdate('Y-m-d H:i:s'),
        'created_at'=>gmdate('Y-m-d H:i:s'),
        'updated_at'=>gmdate('Y-m-d H:i:s'),
    ]);
}

function mg_it_pppm(PDO $pdo,int $merchantId,string $runId): array
{
    $now=gmdate('Y-m-d H:i:s');
    $sourceId=mg_it_insert($pdo,'pppm_sources',[
        'public_id'=>mg_public_uuid(),'owner_user_id'=>$merchantId,'source_type'=>'behavior','provider'=>'behavior',
        'name'=>'Behavior Source','status'=>'active','created_at'=>$now,'updated_at'=>$now,
    ]);
    $requestId=mg_it_insert($pdo,'pppm_issuance_requests',[
        'public_id'=>mg_public_uuid(),'source_id'=>$sourceId,'issuer_user_id'=>$merchantId,'merchant_user_id'=>$merchantId,
        'source_reference'=>'behavior-'.$runId,'item_type'=>'gift','funding_type'=>'merchant_funded','quantity'=>1,
        'unit_value_cents'=>2500,'currency'=>'USD','title'=>'Behavior Gift','status'=>'issued','issued_count'=>1,
        'requested_at'=>$now,'completed_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ]);
    $publicId=mg_pppm_item_id();
    $itemId=mg_it_insert($pdo,'pppm_items',[
        'public_id'=>$publicId,'issuance_request_id'=>$requestId,'source_id'=>$sourceId,'unit_sequence'=>1,
        'item_type'=>'gift','funding_type'=>'merchant_funded','issuer_user_id'=>$merchantId,'merchant_user_id'=>$merchantId,
        'owner_user_id'=>$merchantId,'source_reference'=>'behavior-'.$runId,'title_snapshot'=>'Behavior Gift',
        'value_cents_snapshot'=>2500,'currency_snapshot'=>'USD','status'=>'available','version_no'=>1,
        'issued_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$itemId,'public_id'=>$publicId];
}

function mg_it_location(PDO $pdo,int $merchantId,string $runId): array
{
    $now=gmdate('Y-m-d H:i:s');
    $workspaceId=mg_it_insert($pdo,'merchant_workspaces',[
        'public_id'=>mg_public_uuid(),'merchant_user_id'=>$merchantId,'display_name'=>'Behavior Merchant',
        'default_currency'=>'USD','timezone'=>'UTC','status'=>'active','eligibility_status'=>'eligible',
        'onboarding_percent'=>100,'created_at'=>$now,'updated_at'=>$now,
    ]);

    $columns=array_column($pdo->query('SHOW COLUMNS FROM merchant_locations')->fetchAll(PDO::FETCH_ASSOC),'Field');
    $locationPublic=mg_public_uuid();
    if(in_array('workspace_id',$columns,true)){
        $locationId=mg_it_insert($pdo,'merchant_locations',[
            'public_id'=>$locationPublic,'workspace_id'=>$workspaceId,'name'=>'Behavior Location',
            'location_code'=>'LOC-'.$runId,'country_code'=>'US','timezone'=>'UTC','status'=>'active',
            'is_primary'=>1,'created_at'=>$now,'updated_at'=>$now,
        ]);
    }else{
        $locationId=mg_it_insert($pdo,'merchant_locations',[
            'public_id'=>$locationPublic,'merchant_user_id'=>$merchantId,'name'=>'Behavior Location',
            'country_code'=>'US','status'=>'active','created_at'=>$now,'updated_at'=>$now,
        ]);
    }

    $code='MG-'.$runId;
    mg_it_insert($pdo,'merchant_claim_codes',[
        'public_id'=>mg_public_uuid(),'merchant_user_id'=>$merchantId,'location_id'=>$locationId,
        'label'=>'Behavior Code','code_hash'=>mg_location_claim_hash($code),'code_last4'=>substr($code,-4),
        'status'=>'active','usage_count'=>0,'created_by_user_id'=>$merchantId,'created_at'=>$now,'updated_at'=>$now,
    ]);
    return ['id'=>$locationId,'public_id'=>$locationPublic,'code'=>$code];
}

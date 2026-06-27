<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
$pdo=mg_db();
$q=mb_substr(trim((string)($_GET['q']??'')),0,80);
if(mb_strlen($q)<2)mg_ok(['recipients'=>[]]);
$like='%'.$q.'%';

function mg_ac_table_exists(PDO $pdo,string $table): bool
{
    try{
        $stmt=$pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }catch(Throwable){return false;}
}

function mg_ac_column_exists(PDO $pdo,string $table,string $column): bool
{
    static $cache=[];
    $key=$table.'.'.$column;
    if(array_key_exists($key,$cache))return $cache[$key];
    try{
        $stmt=$pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        $cache[$key]=(bool)$stmt->fetchColumn();
    }catch(Throwable){$cache[$key]=false;}
    return $cache[$key];
}

function mg_ac_email_hint(string $email): string
{
    $email=trim($email);
    if($email===''||!str_contains($email,'@'))return '';
    [$local,$domain]=explode('@',$email,2);
    $localHint=mb_substr($local,0,1).str_repeat('•',max(2,min(6,mb_strlen($local)-1)));
    return $localHint.'@'.$domain;
}

function mg_ac_user_identity_expr(PDO $pdo,string $alias='u'): string
{
    return mg_ac_column_exists($pdo,'users','public_id') ? "{$alias}.public_id" : "{$alias}.email";
}

function mg_ac_user_display_expr(PDO $pdo,string $alias='u'): string
{
    $parts=[];
    foreach(['display_name','full_name','email'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$parts[]="{$alias}.{$column}";
    }
    return 'COALESCE('.implode(',',array_unique($parts ?: ["{$alias}.email"])).')';
}

function mg_ac_user_search_clause(PDO $pdo,string $alias='u'): string
{
    $parts=[];
    foreach(['display_name','full_name','email','public_id'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$parts[]="{$alias}.{$column} LIKE ?";
    }
    return '('.implode(' OR ',$parts ?: ["{$alias}.email LIKE ?"]).')';
}

function mg_ac_user_search_params(PDO $pdo,string $like): array
{
    $params=[];
    foreach(['display_name','full_name','email','public_id'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$params[]=$like;
    }
    return $params ?: [$like];
}

function mg_ac_user_status_clause(PDO $pdo,string $alias='u'): string
{
    return mg_ac_column_exists($pdo,'users','status') ? " AND {$alias}.status='active'" : '';
}

function mg_ac_append_unique_recipients(array &$rows,array $incoming): void
{
    $seen=array_fill_keys(array_map(static fn(array $row): string=>(string)$row['recipient_user_id'],$rows),true);
    foreach($incoming as $row){
        $key=(string)($row['recipient_user_id']??'');
        if($key===''||isset($seen[$key]))continue;
        $rows[]=$row;
        $seen[$key]=true;
    }
}

function mg_ac_recipient_query(PDO $pdo,string $source,string $joinSql,string $whereSql,array $baseParams,int $limit,string $like): array
{
    if($limit<1)return [];
    $identity=mg_ac_user_identity_expr($pdo,'u');
    $display=mg_ac_user_display_expr($pdo,'u');
    $search=mg_ac_user_search_clause($pdo,'u');
    $status=mg_ac_user_status_clause($pdo,'u');
    $sql="SELECT {$identity} recipient_user_id,{$display} display_name,u.email,? source
        {$joinSql}
        WHERE {$whereSql} {$status} AND {$search}
        ORDER BY {$display}
        LIMIT {$limit}";
    $stmt=$pdo->prepare($sql);
    $stmt->execute(array_merge([$source],$baseParams,mg_ac_user_search_params($pdo,$like)));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$rows=[];
$remaining=10;
$relationshipConfigs=[];

if(mg_ac_table_exists($pdo,'social_follows')){
    $relationshipConfigs[]=['source'=>'following','join'=>'FROM social_follows f INNER JOIN users u ON u.id=f.followed_user_id','where'=>'f.follower_user_id=? AND u.id<>? AND COALESCE(f.status,\'active\')=\'active\''];
}
if(mg_ac_table_exists($pdo,'user_followers')){
    $relationshipConfigs[]=['source'=>'follower','join'=>'FROM user_followers f INNER JOIN users u ON u.id=f.follower_user_id','where'=>'f.user_id=? AND u.id<>?'];
}
if(mg_ac_table_exists($pdo,'followers')){
    $relationshipConfigs[]=['source'=>'follower','join'=>'FROM followers f INNER JOIN users u ON u.id=f.follower_user_id','where'=>'f.user_id=? AND u.id<>?'];
}

foreach($relationshipConfigs as $config){
    if($remaining<1)continue;
    try{
        $incoming=mg_ac_recipient_query($pdo,$config['source'],$config['join'],$config['where'],[(int)$user['id'],(int)$user['id']],$remaining,$like);
        mg_ac_append_unique_recipients($rows,$incoming);
        $remaining=10-count($rows);
    }catch(Throwable $error){
        if(function_exists('mg_security_log'))mg_security_log('warning','action_center.recipient_relationship_search_failed','Recipient relationship search failed.',['source'=>$config['source'],'exception'=>$error->getMessage()],(int)$user['id']);
    }
}

if(count($rows)<10){
    try{
        $remaining=10-count($rows);
        $incoming=mg_ac_recipient_query($pdo,'user','FROM users u','u.id<>?',[(int)$user['id']],$remaining,$like);
        mg_ac_append_unique_recipients($rows,$incoming);
    }catch(Throwable $error){
        if(function_exists('mg_security_log'))mg_security_log('error','action_center.recipient_user_search_failed','Recipient user search failed.',['exception'=>$error->getMessage()],(int)$user['id']);
        mg_ok(['recipients'=>[]]);
    }
}

mg_ok(['recipients'=>array_map(static function(array $row): array{
    return [
        'recipient_user_id'=>(string)$row['recipient_user_id'],
        'display_name'=>(string)($row['display_name']??'Recipient'),
        'email_hint'=>mg_ac_email_hint((string)($row['email']??'')),
        'source'=>(string)($row['source']??'user'),
    ];
},$rows)]);

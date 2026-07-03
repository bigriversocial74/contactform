<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center.php';

mg_require_method('GET');
$user=mg_require_api_user();
$pdo=mg_db();
$q=mb_substr(trim((string)($_GET['q']??'')),0,80);
if(mb_strlen($q)<2)mg_ok(['recipients'=>[]]);
$like='%'.$q.'%';
$viewerId=(int)$user['id'];

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

function mg_ac_safe_url(?string $url): string
{
    $url=trim((string)$url);
    if($url===''||strlen($url)>500||strpbrk($url,"\r\n\t")!==false)return '';
    if($url[0]==='/'&&!str_starts_with($url,'//'))return $url;
    return preg_match('#^https://#i',$url)===1&&filter_var($url,FILTER_VALIDATE_URL)?$url:'';
}

function mg_ac_user_identity_expr(PDO $pdo,string $alias='u'): string
{
    return mg_ac_column_exists($pdo,'users','public_id') ? "{$alias}.public_id" : "{$alias}.email";
}

function mg_ac_user_display_expr(PDO $pdo,string $alias='u',string $profileAlias='pp'): string
{
    $parts=[];
    if(mg_ac_table_exists($pdo,'public_profiles')&&mg_ac_column_exists($pdo,'public_profiles','display_name'))$parts[]="{$profileAlias}.display_name";
    foreach(['display_name','full_name','email'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$parts[]="{$alias}.{$column}";
    }
    return 'COALESCE('.implode(',',array_unique($parts ?: ["{$alias}.email"])).')';
}

function mg_ac_user_search_clause(PDO $pdo,string $alias='u',string $profileAlias='pp'): string
{
    $parts=[];
    foreach(['display_name','full_name','email','public_id'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$parts[]="{$alias}.{$column} LIKE ?";
    }
    if(mg_ac_table_exists($pdo,'public_profiles')){
        foreach(['display_name','slug','public_id','profile_type'] as $column){
            if(mg_ac_column_exists($pdo,'public_profiles',$column))$parts[]="{$profileAlias}.{$column} LIKE ?";
        }
    }
    return '('.implode(' OR ',$parts ?: ["{$alias}.email LIKE ?"]).')';
}

function mg_ac_user_search_params(PDO $pdo,string $like): array
{
    $params=[];
    foreach(['display_name','full_name','email','public_id'] as $column){
        if(mg_ac_column_exists($pdo,'users',$column))$params[]=$like;
    }
    if(mg_ac_table_exists($pdo,'public_profiles')){
        foreach(['display_name','slug','public_id','profile_type'] as $column){
            if(mg_ac_column_exists($pdo,'public_profiles',$column))$params[]=$like;
        }
    }
    return $params ?: [$like];
}

function mg_ac_user_status_clause(PDO $pdo,string $alias='u'): string
{
    return mg_ac_column_exists($pdo,'users','status') ? " AND {$alias}.status='active'" : '';
}

function mg_ac_profile_join(PDO $pdo): string
{
    if(!mg_ac_table_exists($pdo,'public_profiles'))return '';
    return " LEFT JOIN public_profiles pp ON pp.user_id=u.id AND pp.status='active' AND pp.visibility IN ('public','unlisted')";
}

function mg_ac_profile_select(PDO $pdo,int $viewerId): string
{
    $select=[];
    if(mg_ac_table_exists($pdo,'public_profiles')){
        $select[]="pp.public_id recipient_profile_id";
        $select[]="pp.slug recipient_slug";
        $select[]=mg_ac_column_exists($pdo,'public_profiles','avatar_url')?"pp.avatar_url avatar_url":"NULL avatar_url";
        $select[]=mg_ac_column_exists($pdo,'public_profiles','profile_type')?"pp.profile_type profile_type":"NULL profile_type";
    }else{
        $select[]="NULL recipient_profile_id";
        $select[]="NULL recipient_slug";
        $select[]="NULL avatar_url";
        $select[]="NULL profile_type";
    }
    if(mg_ac_table_exists($pdo,'social_follows')){
        $select[]="EXISTS(SELECT 1 FROM social_follows sf WHERE sf.follower_user_id=".(int)$viewerId." AND sf.followed_user_id=u.id AND sf.status='active') is_following";
    }else{
        $select[]="0 is_following";
    }
    return ','.implode(',',$select);
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

function mg_ac_recipient_query(PDO $pdo,int $viewerId,string $source,string $joinSql,string $whereSql,array $baseParams,int $limit,string $like): array
{
    if($limit<1)return [];
    $identity=mg_ac_user_identity_expr($pdo,'u');
    $display=mg_ac_user_display_expr($pdo,'u','pp');
    $search=mg_ac_user_search_clause($pdo,'u','pp');
    $status=mg_ac_user_status_clause($pdo,'u');
    $profileJoin=mg_ac_profile_join($pdo);
    $profileSelect=mg_ac_profile_select($pdo,$viewerId);
    $sql="SELECT {$identity} recipient_user_id,{$display} display_name,u.email,? source{$profileSelect}
        {$joinSql}{$profileJoin}
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
    $relationshipConfigs[]=['table'=>'social_follows','source'=>'following','join'=>'FROM social_follows f INNER JOIN users u ON u.id=f.followed_user_id','where'=>'f.follower_user_id=? AND u.id<>? AND COALESCE(f.status,\'active\')=\'active\''];
}
if(mg_ac_table_exists($pdo,'user_followers')){
    $relationshipConfigs[]=['table'=>'user_followers','source'=>'follower','join'=>'FROM user_followers f INNER JOIN users u ON u.id=f.follower_user_id','where'=>'f.user_id=? AND u.id<>?'];
}
if(mg_ac_table_exists($pdo,'followers')){
    $relationshipConfigs[]=['table'=>'followers','source'=>'follower','join'=>'FROM followers f INNER JOIN users u ON u.id=f.follower_user_id','where'=>'f.user_id=? AND u.id<>?'];
}

foreach($relationshipConfigs as $config){
    if($remaining<1)continue;
    try{
        $incoming=mg_ac_recipient_query($pdo,$viewerId,$config['source'],$config['join'],$config['where'],[$viewerId,$viewerId],$remaining,$like);
        mg_ac_append_unique_recipients($rows,$incoming);
        $remaining=10-count($rows);
    }catch(Throwable $error){
        if(function_exists('mg_security_log'))mg_security_log('warning','action_center.recipient_relationship_search_failed','Recipient relationship search failed.',['table'=>$config['table'],'exception'=>$error->getMessage()],$viewerId);
    }
}

if(count($rows)<10){
    try{
        $remaining=10-count($rows);
        $incoming=mg_ac_recipient_query($pdo,$viewerId,'user','FROM users u','u.id<>?',[$viewerId],$remaining,$like);
        mg_ac_append_unique_recipients($rows,$incoming);
    }catch(Throwable $error){
        if(function_exists('mg_security_log'))mg_security_log('error','action_center.recipient_user_search_failed','Recipient user search failed.',['exception'=>$error->getMessage()],$viewerId);
        mg_ok(['recipients'=>[]]);
    }
}

mg_ok(['recipients'=>array_map(static function(array $row): array{
    $profileId=(string)($row['recipient_profile_id']??'');
    $slug=(string)($row['recipient_slug']??'');
    return [
        'recipient_user_id'=>(string)$row['recipient_user_id'],
        'recipient_profile_id'=>$profileId,
        'profile_id'=>$profileId,
        'recipient_slug'=>$slug,
        'profile_slug'=>$slug,
        'display_name'=>(string)($row['display_name']??'Recipient'),
        'email_hint'=>mg_ac_email_hint((string)($row['email']??'')),
        'avatar_url'=>mg_ac_safe_url($row['avatar_url']??''),
        'profile_type'=>(string)($row['profile_type']??'profile'),
        'is_following'=>(bool)($row['is_following']??false),
        'source'=>(string)($row['source']??'user'),
    ];
},$rows)]);

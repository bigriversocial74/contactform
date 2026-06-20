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

function mg_ac_email_hint(string $email): string
{
    $email=trim($email);
    if($email===''||!str_contains($email,'@'))return '';
    [$local,$domain]=explode('@',$email,2);
    $localHint=mb_substr($local,0,1).str_repeat('•',max(2,min(6,mb_strlen($local)-1)));
    return $localHint.'@'.$domain;
}

$rows=[];
if(mg_ac_table_exists($pdo,'user_followers')){
    $stmt=$pdo->prepare("SELECT u.public_id recipient_user_id,COALESCE(u.display_name,u.full_name,u.email) display_name,u.email,'follower' source
        FROM user_followers f
        INNER JOIN users u ON u.id=f.follower_user_id
        WHERE f.user_id=? AND u.id<>? AND u.status='active' AND (COALESCE(u.display_name,u.full_name,'') LIKE ? OR u.email LIKE ? OR u.public_id LIKE ?)
        ORDER BY COALESCE(u.display_name,u.full_name,u.email)
        LIMIT 10");
    $stmt->execute([(int)$user['id'],(int)$user['id'],$like,$like,$like]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}elseif(mg_ac_table_exists($pdo,'followers')){
    $stmt=$pdo->prepare("SELECT u.public_id recipient_user_id,COALESCE(u.display_name,u.full_name,u.email) display_name,u.email,'follower' source
        FROM followers f
        INNER JOIN users u ON u.id=f.follower_user_id
        WHERE f.user_id=? AND u.id<>? AND u.status='active' AND (COALESCE(u.display_name,u.full_name,'') LIKE ? OR u.email LIKE ? OR u.public_id LIKE ?)
        ORDER BY COALESCE(u.display_name,u.full_name,u.email)
        LIMIT 10");
    $stmt->execute([(int)$user['id'],(int)$user['id'],$like,$like,$like]);
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
}

if(count($rows)<10){
    $stmt=$pdo->prepare("SELECT public_id recipient_user_id,COALESCE(display_name,full_name,email) display_name,email,'user' source
        FROM users
        WHERE id<>? AND status='active' AND (COALESCE(display_name,full_name,'') LIKE ? OR email LIKE ? OR public_id LIKE ?)
        ORDER BY COALESCE(display_name,full_name,email)
        LIMIT ?");
    $stmt->execute([(int)$user['id'],$like,$like,$like,10-count($rows)]);
    $seen=array_fill_keys(array_map(static fn(array $row): string=>(string)$row['recipient_user_id'],$rows),true);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        if(isset($seen[(string)$row['recipient_user_id']]))continue;
        $rows[]=$row;
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

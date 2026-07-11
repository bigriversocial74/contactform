<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept, Content-Type');
header('Vary: Origin');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
    http_response_code(204);
    exit;
}
mg_require_method('GET');
$ref = strtolower(trim((string)($_GET['campaign'] ?? $_GET['id'] ?? $_GET['slug'] ?? '')));
if ($ref === '' || mb_strlen($ref)>160 || preg_match('/^[a-z0-9_-]+$/',$ref)!==1) mg_fail('Invalid Loyalty Quest.',422);
$pdo = mg_db();
$stmt = $pdo->prepare("SELECT c.public_id,c.public_slug,c.title,c.description,c.rules_json,c.starts_at,c.ends_at,
    COALESCE(pp.display_name,mw.display_name,u.display_name,u.full_name,'Microgifter Merchant') merchant_name,
    rt.title reward_title,rt.description reward_description,rt.value_type,rt.value_amount_cents,rt.value_percent,rt.currency
    FROM campaigns c
    INNER JOIN users u ON u.id=c.merchant_user_id
    LEFT JOIN public_profiles pp ON pp.user_id=c.merchant_user_id
    LEFT JOIN merchant_workspaces mw ON mw.merchant_user_id=c.merchant_user_id
    LEFT JOIN reward_templates rt ON rt.id=c.reward_template_id AND rt.status='active'
    WHERE c.campaign_type='loyalty_quest' AND c.status='active' AND (c.public_id=? OR c.public_slug=?)
      AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>NOW())
    LIMIT 1");
$stmt->execute([$ref,$ref]);
$row=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$row) mg_fail('Loyalty Quest not available.',404);
$rules=json_decode((string)($row['rules_json']??''),true);if(!is_array($rules))$rules=[];
$visibility=(string)($rules['visibility']??'public');
if($visibility!=='public') mg_fail('This Loyalty Quest is not available for public embedding.',403);
$creative=is_array($rules['creative']??null)?$rules['creative']:[];
$value='';
if(($row['value_type']??'')==='percent'&&$row['value_percent']!==null)$value=rtrim(rtrim(number_format((float)$row['value_percent'],2),'0'),'.').'%';
elseif($row['value_amount_cents']!==null)$value=strtoupper((string)($row['currency']??'USD')).' '.number_format(((int)$row['value_amount_cents'])/100,2);
$base=rtrim((string)(defined('MG_APP_URL')?MG_APP_URL:''),'/');
if($base===''){$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=preg_replace('/[^A-Za-z0-9.:-]/','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';$base=$scheme.'://'.$host;}
$publicRef=(string)($row['public_slug']?:$row['public_id']);
mg_ok(['quest'=>[
    'id'=>(string)$row['public_id'],'title'=>(string)($creative['headline']??$row['title']),'description'=>(string)$row['description'],
    'merchant'=>(string)$row['merchant_name'],'reward_title'=>(string)($row['reward_title']??'Microgifter reward'),'reward_description'=>(string)($row['reward_description']??''),'reward_value'=>$value,
    'image_url'=>(string)($creative['cover_url']??$rules['cover_image_url']??''),'image_alt'=>(string)($creative['image_alt']??$rules['cover_image_alt']??''),
    'cta'=>(string)($creative['cta']??'Start Loyalty Quest'),'terms'=>(string)($creative['terms']??'Terms and availability apply.'),'accent'=>(string)($creative['accent']??'#111827'),
    'url'=>$base.'/loyalty-quest.php?campaign='.rawurlencode($publicRef),'ends_at'=>$row['ends_at']??null,
]]);

<?php
declare(strict_types=1);
require_once __DIR__ . '/_participant.php';

mg_require_method('GET');
$ref=strtolower(trim((string)($_GET['campaign']??$_GET['campaign_id']??$_GET['slug']??'')));
if($ref===''||mb_strlen($ref)>160)mg_fail('Invalid Loyalty Quest.',422);
$pdo=mg_db();
$campaign=mg_lqp_campaign($pdo,$ref,false);
$user=mg_current_user();
$participation=null;
$evidence=[];
if($user){
    $stmt=$pdo->prepare('SELECT lqp.*,wi.public_id wallet_item_public_id,wi.status wallet_item_status,wi.expires_at wallet_expires_at FROM loyalty_quest_participations lqp LEFT JOIN wallet_items wi ON wi.id=lqp.wallet_item_id WHERE lqp.campaign_id=? AND lqp.participant_user_id=? LIMIT 1');
    $stmt->execute([(int)$campaign['id'],(int)$user['id']]);
    $participation=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
    if($participation){
        $ev=$pdo->prepare('SELECT public_id,evidence_type,status,latitude,longitude,accuracy_meters,distance_meters,proof_url,proof_note,reference_id,review_note,verified_at,rejected_at,created_at FROM loyalty_quest_evidence WHERE participation_id=? AND participant_user_id=? ORDER BY created_at DESC LIMIT 25');
        $ev->execute([(int)$participation['id'],(int)$user['id']]);
        $evidence=$ev->fetchAll(PDO::FETCH_ASSOC)?:[];
    }
}
$rules=$campaign['rules'];
$value='';
if(($campaign['value_type']??'')==='percent'&&$campaign['value_percent']!==null)$value=rtrim(rtrim(number_format((float)$campaign['value_percent'],2),'0'),'.').'%';
elseif($campaign['value_amount_cents']!==null)$value=strtoupper((string)$campaign['currency']).' '.number_format(((int)$campaign['value_amount_cents'])/100,2);
$location=[
    'id'=>$campaign['location_public_id']??null,'name'=>$campaign['location_name']??null,'address'=>$campaign['address_line1']??null,
    'city'=>$campaign['city']??null,'region'=>$campaign['region']??null,'postal_code'=>$campaign['postal_code']??null,
    'latitude'=>$campaign['location_latitude']!==null?(float)$campaign['location_latitude']:null,
    'longitude'=>$campaign['location_longitude']!==null?(float)$campaign['location_longitude']:null,
    'radius_meters'=>(int)($rules['radius_meters']??$campaign['location_radius']??150),
];
mg_ok([
 'quest'=>[
   'id'=>(string)$campaign['public_id'],'slug'=>$campaign['public_slug']??null,'title'=>(string)$campaign['title'],'description'=>(string)($campaign['description']??''),
   'instructions'=>(string)($campaign['form_description']??$campaign['description']??''),'success_message'=>(string)($campaign['success_message']??'Quest completed.'),
   'action_type'=>(string)($rules['action_type']??''),'verification_type'=>(string)($rules['verification_type']??'manual_review'),
   'required_count'=>mg_lqp_required_count($campaign),'starts_at'=>$campaign['starts_at']??null,'ends_at'=>$campaign['ends_at']??null,
   'merchant'=>['name'=>(string)$campaign['merchant_name'],'slug'=>$campaign['merchant_slug']??null,'avatar_url'=>$campaign['merchant_avatar']??null],
   'location'=>$location,'reward'=>['title'=>(string)$campaign['reward_template_title'],'description'=>(string)($campaign['reward_template_description']??''),'value'=>$value,'redemption_instructions'=>(string)($campaign['redemption_instructions']??'')],
   'rules'=>['proof_required'=>!empty($rules['proof_required']),'staff_confirmation_required'=>!empty($rules['staff_confirmation_required']),'invite_only'=>(string)($rules['visibility']??'public')==='invite_only'],
 ],
 'authenticated'=>(bool)$user,
 'participation'=>$participation?[
   'id'=>(string)$participation['public_id'],'status'=>(string)$participation['status'],'progress_count'=>(int)$participation['progress_count'],'required_count'=>(int)$participation['required_count'],'completion_percent'=>(int)$participation['completion_percent'],
   'joined_at'=>$participation['joined_at']??null,'submitted_at'=>$participation['submitted_at']??null,'completed_at'=>$participation['completed_at']??null,
   'wallet_item_id'=>$participation['wallet_item_public_id']??null,'wallet_status'=>$participation['wallet_item_status']??null,'wallet_expires_at'=>$participation['wallet_expires_at']??null,
 ]:null,
 'evidence'=>$evidence,
]);

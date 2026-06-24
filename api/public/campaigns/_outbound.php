<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/communications/_delivery.php';
require_once dirname(__DIR__, 3) . '/includes/mail.php';
require_once __DIR__ . '/_email_suppression.php';

function mg_public_campaign_outbound_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function mg_public_campaign_outbound_render(array $campaign, array $contact, string $messageType, array $context = []): array
{
    $name = trim((string)($contact['name'] ?? '')) ?: 'there';
    $title = (string)($campaign['reward_template_title'] ?? $campaign['title'] ?? 'your Microgifter reward');
    $campaignTitle = (string)($campaign['title'] ?? 'Microgifter campaign');
    $email = strtolower(trim((string)($contact['email'] ?? '')));
    $campaignId = (int)($campaign['id'] ?? 0);
    $merchantId = (int)($campaign['merchant_user_id'] ?? 0);
    $inboxUrl = mg_app_base_url() . '/inbox.php';
    $unsubscribeUrl = filter_var($email,FILTER_VALIDATE_EMAIL) ? mg_campaign_email_unsubscribe_url($merchantId,$campaignId,$email,'campaign') : mg_app_base_url();
    $subject = $messageType === 'newsletter_signup_confirmation' ? 'Your Microgifter reward is waiting' : 'Microgifter campaign update';
    $body = '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Hi ' . mg_mail_escape($name) . ', thanks for joining ' . mg_mail_escape($campaignTitle) . '.</p>'
        . '<p style="margin:0 0 16px;color:#334155;font-size:16px;line-height:1.6;">Your reward: <strong>' . mg_mail_escape($title) . '</strong>.</p>'
        . mg_email_button($inboxUrl, 'Open Microgifter inbox')
        . '<p style="margin:0 0 16px;color:#64748b;font-size:13px;line-height:1.6;">If you do not have an account yet, create one with this email address and the reward will link to your inbox.</p>'
        . '<p style="margin:18px 0 0;color:#94a3b8;font-size:12px;line-height:1.6;"><a href="' . mg_mail_escape($unsubscribeUrl) . '" style="color:#64748b;">Unsubscribe from this campaign</a></p>';
    return ['subject'=>$subject,'html'=>mg_email_layout('Your reward is waiting',$body,'Open your Microgifter reward.'),'text'=>"Hi {$name}, thanks for joining {$campaignTitle}. Your reward: {$title}. Open your Microgifter inbox: {$inboxUrl}\nUnsubscribe: {$unsubscribeUrl}"];
}

function mg_public_campaign_queue_outbound(PDO $pdo, array $campaign, array $contact, string $messageType, array $context = []): array
{
    $merchantId=(int)($campaign['merchant_user_id']??0);$campaignId=(int)($campaign['id']??0);$contactId=(int)($contact['id']??0);
    if($merchantId<1||$campaignId<1||$contactId<1)return ['queued'=>false,'reason'=>'missing_context'];
    $eventId=mg_public_campaign_outbound_uuid();$email=strtolower(trim((string)($contact['email']??'')));
    if(filter_var($email,FILTER_VALIDATE_EMAIL)&&mg_campaign_email_is_suppressed($pdo,$merchantId,$campaignId,$email)){
        $payload=['message_type'=>$messageType,'campaign_type'=>(string)($campaign['campaign_type']??'unknown'),'campaign_public_id'=>(string)($campaign['public_id']??''),'contact_public_id'=>(string)($contact['public_id']??''),'email_hash'=>mg_campaign_email_hash($email),'outbound_email_suppressed'=>true];
        $stmt=$pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');$stmt->execute([$eventId,$merchantId,$campaignId,null,$contactId,'outbound_email.suppressed',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        return ['queued'=>false,'reason'=>'suppressed','event_id'=>$eventId,'message_type'=>$messageType];
    }
    $rendered=mg_public_campaign_outbound_render($campaign,$contact,$messageType,$context);
    $payload=$context+$rendered+['message_type'=>$messageType,'campaign_type'=>(string)($campaign['campaign_type']??'unknown'),'campaign_public_id'=>(string)($campaign['public_id']??''),'contact_public_id'=>(string)($contact['public_id']??''),'email'=>$email,'outbound_email_pending'=>true];
    $stmt=$pdo->prepare('INSERT INTO campaign_events (public_id,merchant_user_id,campaign_id,wallet_item_id,contact_id,event_type,event_context_json,created_at) VALUES (?,?,?,?,?,?,?,NOW())');$stmt->execute([$eventId,$merchantId,$campaignId,null,$contactId,'outbound_email.queued',json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $delivery=['queued'=>false,'reason'=>'invalid_email'];
    if(filter_var($email,FILTER_VALIDATE_EMAIL)){
        try{$stableKey='campaign-email:'.$messageType.':'.(string)($contact['public_id']??$contactId);$delivery=mg_delivery_enqueue($pdo,['idempotency_key'=>$stableKey,'event_type'=>'campaign.outbound_email','category'=>'campaign','channel'=>'email','template_key'=>'campaign.'.$messageType,'recipient_user_id'=>(int)($context['recipient_user_id']??$contact['user_id']??0),'recipient_snapshot'=>['email'=>$email,'name'=>(string)($contact['name']??'')],'payload'=>$payload,'max_attempts'=>3]);}
        catch(Throwable $error){mg_security_log('warning','campaign.outbound_enqueue_failed','Unable to create delivery job.',['exception_class'=>$error::class,'message'=>$error->getMessage(),'campaign_id'=>$campaignId,'contact_id'=>$contactId]);$delivery=['queued'=>false,'reason'=>'delivery_enqueue_failed'];}
    }
    return ['queued'=>true,'event_id'=>$eventId,'message_type'=>$messageType,'delivery'=>$delivery];
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/hosted-game-standard-core.php';

function mg_hosted_game_standard_bridge_token(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
}

function mg_hosted_game_standard_valid_bridge_token(string $token): bool
{
    return preg_match('/^[A-Za-z0-9_-]{40,64}$/',$token)===1;
}

function mg_hosted_game_standard_iframe_sandbox(array $manifest): string
{
    $values=['allow-scripts'];
    foreach(['pointer_lock'=>'allow-pointer-lock','forms'=>'allow-forms','modals'=>'allow-modals','popups'=>'allow-popups','downloads'=>'allow-downloads'] as $capability=>$attribute){
        if(mg_hosted_game_standard_has_capability($manifest,$capability))$values[]=$attribute;
    }
    return implode(' ',$values);
}

function mg_hosted_game_standard_iframe_allow(array $manifest): string
{
    $values=[];
    if(mg_hosted_game_standard_has_capability($manifest,'audio'))$values[]='autoplay';
    if(mg_hosted_game_standard_has_capability($manifest,'fullscreen'))$values[]='fullscreen';
    if(mg_hosted_game_standard_has_capability($manifest,'gamepad'))$values[]='gamepad';
    if(mg_hosted_game_standard_has_capability($manifest,'motion')){$values[]='accelerometer';$values[]='gyroscope';}
    if(mg_hosted_game_standard_has_capability($manifest,'clipboard_write'))$values[]='clipboard-write';
    return implode('; ',$values);
}

function mg_hosted_game_standard_csp(array $manifest): string
{
    if((string)($manifest['standard']['compliance']??'legacy')!=='standard'){
        return "default-src * data: blob:; script-src * 'unsafe-inline' 'unsafe-eval' blob:; style-src * 'unsafe-inline'; img-src * data: blob:; media-src * data: blob:; font-src * data:; connect-src * data: blob:; worker-src * blob:; child-src * blob:; frame-ancestors 'self'; object-src 'none'; base-uri 'self'";
    }
    $connect=[];
    foreach($manifest['network']['connect']??[] as $source)$connect[]=$source==='self'?"'self'":$source;
    $connectDirective=$connect===[]?"'none'":implode(' ',$connect);
    $formAction=mg_hosted_game_standard_has_capability($manifest,'forms')?"'self'":"'none'";
    return "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' data: blob:; font-src 'self' data:; connect-src {$connectDirective}; worker-src 'self' blob:; child-src blob:; frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action {$formAction}";
}

function mg_hosted_game_standard_event(string $eventType,mixed $event): array
{
    $eventType=strtolower(trim($eventType));
    if(!in_array($eventType,mg_hosted_game_standard_events(),true))throw new InvalidArgumentException('Unsupported Hosted Game Standard event.');
    if(!is_array($event))$event=[];
    mg_hosted_game_json_encode($event,32768);
    return [$eventType,$event];
}

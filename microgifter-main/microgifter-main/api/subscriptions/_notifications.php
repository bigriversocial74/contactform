<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/communications/_communications.php';

function mg_subscription_notify(PDO $pdo,array $subscription,string $type,string $title,string $message,array $payload=[]): string
{
    $userId=(int)$subscription['subscriber_user_id'];
    $warningTypes=[
        'subscription_payment_failed','subscription_paused','subscription_disputed',
        'subscription_refunded','subscription_partial_refund','subscription_chargeback',
    ];
    return mg_create_operational_alert(
        $pdo,
        $userId,
        $type,
        in_array($type,$warningTypes,true)?'warning':'info',
        $title,
        mb_substr($message,0,1000),
        '/account.php?section=subscriptions',
        ['subscription_id'=>(string)($subscription['subscription_public_id']??$subscription['public_id']??'')]+$payload
    );
}

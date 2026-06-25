<?php
declare(strict_types=1);

function mg_public_campaign_throttle(string $scope, string $campaignRef, string $email): void
{
    $ip = mg_client_ip() ?: 'unknown';
    $campaignRef = strtolower(trim($campaignRef));
    $email = strtolower(trim($email));
    mg_rate_limit('public_campaign.' . $scope . '.ip', $scope . ':ip:' . $ip, 60, 3600);
    if ($campaignRef !== '') mg_rate_limit('public_campaign.' . $scope . '.campaign_ip', $scope . ':campaign:' . $campaignRef . ':ip:' . $ip, 20, 3600);
    if ($email !== '') mg_rate_limit('public_campaign.' . $scope . '.email', $scope . ':email:' . hash('sha256', $email), 12, 3600);
}

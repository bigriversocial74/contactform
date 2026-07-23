<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/creator-campaign-pilot.php';
require_once dirname(__DIR__) . '/creator-campaigns.php';
require_once dirname(__DIR__) . '/creator-campaigns/builder-service.php';

final class MgCreatorCampaignOnboardingException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $onboardingCode = 'CREATOR_CAMPAIGN_ONBOARDING_INVALID'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function onboardingCode(): string { return $this->onboardingCode; }
}

const MG_CREATOR_CAMPAIGN_ONBOARDING_STATUSES = ['invited','enrolled','in_progress','ready','active','completed'];
const MG_CREATOR_CAMPAIGN_ONBOARDING_STEPS = [
    1 => ['key'=>'enrollment','label'=>'Pilot enrollment'],
    2 => ['key'=>'business','label'=>'Business and campaign profile'],
    3 => ['key'=>'products','label'=>'Product and offer readiness'],
    4 => ['key'=>'financials','label'=>'Compensation and budget guardrails'],
    5 => ['key'=>'eligibility','label'=>'Creator eligibility preferences'],
    6 => ['key'=>'roles','label'=>'Operator and approval roles'],
    7 => ['key'=>'campaign','label'=>'First campaign guided launch'],
    8 => ['key'=>'smoke_test','label'=>'Production smoke test'],
    9 => ['key'=>'launch','label'=>'Launch dashboard'],
];
const MG_CREATOR_CAMPAIGN_ONBOARDING_ROLE_KEYS = [
    'campaign_owner'=>'Campaign owner',
    'application_reviewer'=>'Application reviewer',
    'content_reviewer'=>'Content reviewer',
    'earnings_reviewer'=>'Earnings reviewer',
    'payout_operator'=>'Payout-record operator',
    'emergency_contact'=>'Emergency contact',
];

function mg_creator_campaign_onboarding_json(mixed $value): array
{
    if (is_array($value)) return $value;
    if (!is_string($value) || trim($value) === '') return [];
    try {
        $decoded = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

function mg_creator_campaign_onboarding_encode(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function mg_creator_campaign_onboarding_text(
    mixed $value,
    string $label,
    int $max,
    bool $required = false,
    int $min = 0
): string {
    $text = trim((string)$value);
    $length = mb_strlen($text);
    if (($required && $text === '') || $length > $max || ($text !== '' && $length < $min)) {
        throw new MgCreatorCampaignOnboardingException('Invalid ' . $label . '.', 422, 'CREATOR_CAMPAIGN_ONBOARDING_VALIDATION_FAILED');
    }
    return $text;
}

function mg_creator_campaign_onboarding_date(mixed $value, string $label): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
    if (!$date || $date->format('Y-m-d') !== $text) {
        throw new MgCreatorCampaignOnboardingException('Invalid ' . $label . '.', 422, 'CREATOR_CAMPAIGN_ONBOARDING_VALIDATION_FAILED');
    }
    return $text;
}

function mg_creator_campaign_onboarding_bool(mixed $value): bool
{
    return in_array($value, [1,'1',true,'true','yes','on'], true);
}

function mg_creator_campaign_onboarding_list(mixed $value, int $maxItems = 30, int $maxLength = 120): array
{
    $values = is_array($value) ? $value : (preg_split('/[\r\n,]+/', trim((string)$value)) ?: []);
    $clean = [];
    foreach ($values as $item) {
        $item = trim((string)$item);
        if ($item === '') continue;
        if (mb_strlen($item) > $maxLength) {
            throw new MgCreatorCampaignOnboardingException('One of the list values is too long.');
        }
        $clean[$item] = $item;
        if (count($clean) > $maxItems) {
            throw new MgCreatorCampaignOnboardingException('Too many list values.');
        }
    }
    return array_values($clean);
}

function mg_creator_campaign_onboarding_money(mixed $value, string $label): int
{
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) {
        throw new MgCreatorCampaignOnboardingException('Invalid ' . $label . '.');
    }
    [$whole, $decimal] = array_pad(explode('.', $raw, 2), 2, '');
    $decimal = str_pad($decimal, 2, '0');
    $minor = ((int)$whole * 100) + (int)$decimal;
    if ($minor < 0 || $minor > 100000000000) {
        throw new MgCreatorCampaignOnboardingException('Invalid ' . $label . '.');
    }
    return $minor;
}

function mg_creator_campaign_onboarding_currency(mixed $value): string
{
    $currency = strtoupper(trim((string)$value));
    if ($currency === '') $currency = 'USD';
    if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
        throw new MgCreatorCampaignOnboardingException('Invalid currency.');
    }
    return $currency;
}

function mg_creator_campaign_onboarding_status_label(string $status): string
{
    return [
        'invited'=>'Invited',
        'enrolled'=>'Enrolled',
        'in_progress'=>'Setup in progress',
        'ready'=>'Ready',
        'active'=>'Active',
        'completed'=>'Completed',
    ][$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function mg_creator_campaign_onboarding_step_label(int $step): string
{
    return (string)(MG_CREATOR_CAMPAIGN_ONBOARDING_STEPS[$step]['label'] ?? 'Onboarding');
}

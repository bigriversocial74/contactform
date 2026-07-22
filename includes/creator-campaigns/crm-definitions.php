<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/merchant-crm.php';

function mg_creator_campaign_crm_required_tables(): array
{
    return [
        'merchant_crm_contacts',
        'merchant_crm_contact_events',
        'merchant_crm_creator_campaign_events',
        'merchant_crm_contact_creator_campaigns',
        'merchant_crm_creator_campaign_projection_runs',
    ];
}

function mg_creator_campaign_crm_installed(PDO $pdo): bool
{
    $tables = mg_creator_campaign_crm_required_tables();
    $placeholders = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ({$placeholders})");
    $stmt->execute($tables);
    return (int) $stmt->fetchColumn() === count($tables);
}

function mg_creator_campaign_crm_relationship_types(): array
{
    return ['creator_partner','customer_lead','customer','claimant','redeemer'];
}

function mg_creator_campaign_crm_source_domains(): array
{
    return ['participation','tracking','earning','payout','dispute','message','manual'];
}

function mg_creator_campaign_crm_relationship_rank(string $type): int
{
    return ['creator_partner'=>5,'customer_lead'=>10,'customer'=>20,'claimant'=>30,'redeemer'=>40][$type] ?? 0;
}

function mg_creator_campaign_crm_source_key(string $domain, string $publicId): string
{
    $domain = strtolower(trim($domain));
    $publicId = strtolower(trim($publicId));
    if (!in_array($domain, mg_creator_campaign_crm_source_domains(), true)) {
        throw new InvalidArgumentException('Creator Campaign CRM source domain is invalid.');
    }
    if ($publicId === '' || mb_strlen($publicId) > 120 || preg_match('/^[a-z0-9][a-z0-9:._-]*$/', $publicId) !== 1) {
        throw new InvalidArgumentException('Creator Campaign CRM source identifier is invalid.');
    }
    return mb_substr($domain . ':' . $publicId, 0, 190);
}

function mg_creator_campaign_crm_metadata(mixed $value): array
{
    if ($value === null || $value === '') return [];
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($value)) return [];
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if (strlen($json) > 32000) throw new InvalidArgumentException('Creator Campaign CRM metadata is too large.');
    return $value;
}

function mg_creator_campaign_crm_identity(array $input): array
{
    $userId = isset($input['user_id']) ? (int) $input['user_id'] : 0;
    $email = mg_merchant_crm_email($input['email'] ?? null);
    $phone = mg_merchant_crm_text($input['phone'] ?? null, 80);
    $name = mg_merchant_crm_text($input['name'] ?? $input['display_name'] ?? null, 180);
    return [
        'user_id' => $userId > 0 ? $userId : null,
        'email' => $email,
        'phone' => $phone,
        'name' => $name,
        'resolved' => $userId > 0 || $email !== null || $phone !== null,
    ];
}

function mg_creator_campaign_crm_tracking_identity(array $metadata): array
{
    $candidate = [];
    foreach (['crm_identity','customer_identity','customer','contact'] as $key) {
        if (is_array($metadata[$key] ?? null)) {
            $candidate = $metadata[$key];
            break;
        }
    }
    if ($candidate === []) {
        $candidate = [
            'user_id' => $metadata['customer_user_id'] ?? null,
            'email' => $metadata['customer_email'] ?? null,
            'phone' => $metadata['customer_phone'] ?? null,
            'name' => $metadata['customer_name'] ?? null,
        ];
    }
    return mg_creator_campaign_crm_identity($candidate);
}

function mg_creator_campaign_crm_tracking_relationship(string $eventType): string
{
    return match (strtolower(trim($eventType))) {
        'lead','checkout' => 'customer_lead',
        'purchase' => 'customer',
        'claim' => 'claimant',
        'redemption' => 'redeemer',
        default => throw new InvalidArgumentException('Tracking event is not CRM lifecycle eligible.'),
    };
}

function mg_creator_campaign_crm_generic_event_type(string $domain, string $eventType): string
{
    $eventType = strtolower(trim($eventType));
    $eventType = preg_replace('/[^a-z0-9_:-]+/', '_', $eventType) ?: 'event';
    return mb_substr('creator_campaign_' . $domain . '_' . trim($eventType, '_'), 0, 90);
}

function mg_creator_campaign_crm_relationship_closed(string $eventType, ?string $toStatus): bool
{
    $eventType = strtolower(trim($eventType));
    $toStatus = strtolower(trim((string) $toStatus));
    if (in_array($toStatus, ['declined','withdrawn','removed','completed','cancelled','archived'], true)) return true;
    return str_contains($eventType, 'declined') || str_contains($eventType, 'withdrawn')
        || str_contains($eventType, 'removed') || str_contains($eventType, 'cancelled');
}

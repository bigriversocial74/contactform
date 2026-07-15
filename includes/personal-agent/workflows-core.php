<?php
declare(strict_types=1);

function mg_personal_workflows_require_schema(PDO $pdo): void
{
    foreach ([
        'user_gifting_schedules',
        'user_recurring_gift_programs',
        'user_recurring_gift_runs',
        'user_group_gifts',
        'user_group_gift_participants',
        'user_recipient_data_requests',
        'user_contact_profile_import_fields',
        'user_gift_bundles',
        'user_gift_bundle_items',
        'user_gift_lifecycle_reminders',
    ] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) {
            throw new RuntimeException('Personal Gifting Agent Phase 3 database migration is required.');
        }
    }
}

function mg_personal_workflows_cents(mixed $value, bool $allowNull = true): ?int
{
    if ($value === null || $value === '') return $allowNull ? null : 0;
    if (!is_numeric($value)) throw new InvalidArgumentException('Enter a valid amount.');
    $amount = round((float) $value, 2);
    if ($amount < 0 || $amount > 1000000) throw new InvalidArgumentException('Amount is outside the allowed range.');
    return (int) round($amount * 100);
}

function mg_personal_workflows_money(?int $cents): ?float
{
    return $cents === null ? null : round($cents / 100, 2);
}

function mg_personal_workflows_bool(mixed $value, bool $fallback = false): bool
{
    if ($value === null) return $fallback;
    return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
}

function mg_personal_workflows_context(PDO $pdo, int $userId, array $input): array
{
    return mg_personal_agent_resolve_context($pdo,$userId,(string)($input['context_type'] ?? 'none'),(string)($input['context_id'] ?? ''));
}

function mg_personal_workflows_context_columns(array $context): array
{
    $internal = is_array($context['internal'] ?? null) ? $context['internal'] : [];
    return [
        'list_id' => $internal['list_id'] ?? null,
        'user_contact_id' => $internal['user_contact_id'] ?? null,
        'contact_user_id' => $internal['contact_user_id'] ?? null,
        'plan_id' => ($context['type'] ?? '') === 'plan' ? ($internal['plan_id'] ?? null) : null,
    ];
}

function mg_personal_workflows_notify(PDO $pdo, int $userId, string $type, string $title, string $body, string $actionUrl, ?int $giftId = null): ?int
{
    if ($userId < 1 || !mg_personal_agent_table_exists($pdo, 'notifications')) return null;
    $stmt = $pdo->prepare('INSERT INTO notifications (public_id,user_id,type,title,body,action_url,gift_id,created_at) VALUES (?,?,?,?,?,?,?,NOW())');
    $stmt->execute([
        mg_public_uuid(),$userId,mg_personal_agent_text($type,80),mg_personal_agent_text($title,160),
        mg_personal_agent_nullable_text($body,500),mg_personal_agent_nullable_text($actionUrl,255),$giftId,
    ]);
    return (int) $pdo->lastInsertId();
}

function mg_personal_workflows_user_label(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare("SELECT COALESCE(pp.display_name,u.display_name,u.full_name,'Microgifter user') FROM users u LEFT JOIN public_profiles pp ON pp.user_id=u.id WHERE u.id=? LIMIT 1");
    $stmt->execute([$userId]);
    return mg_personal_agent_text($stmt->fetchColumn() ?: 'Microgifter user',190);
}

function mg_personal_workflows_find_user_by_public_id(PDO $pdo, string $publicId): int
{
    $stmt = $pdo->prepare("SELECT id FROM users WHERE public_id=? AND status='active' LIMIT 1");
    $stmt->execute([mg_personal_agent_text($publicId,80)]);
    $id = (int)($stmt->fetchColumn() ?: 0);
    if ($id < 1) throw new RuntimeException('User not found.');
    return $id;
}

function mg_personal_workflows_next_run(string $from, string $cadence, int $interval): string
{
    try { $date = new DateTimeImmutable($from,new DateTimeZone('UTC')); }
    catch (Throwable) { throw new InvalidArgumentException('Enter a valid recurring start date.'); }
    $interval=max(1,min(52,$interval));
    $next=match($cadence){
        'weekly'=>$date->modify('+'.$interval.' week'),
        'monthly'=>$date->modify('+'.$interval.' month'),
        'quarterly'=>$date->modify('+'.(3*$interval).' month'),
        'yearly'=>$date->modify('+'.$interval.' year'),
        'custom'=>$date->modify('+'.$interval.' day'),
        default=>throw new InvalidArgumentException('Choose a valid recurring cadence.'),
    };
    return $next->format('Y-m-d H:i:s');
}

function mg_personal_workflows_request_scopes(mixed $value): array
{
    $requested=is_array($value)?$value:[];
    $allowed=['profile.gift_preferences','profile.address','profile.birthdate'];
    return array_values(array_unique(array_filter(array_map(
        static fn(mixed $scope):string=>mg_personal_agent_text($scope,100),$requested
    ),static fn(string $scope):bool=>in_array($scope,$allowed,true))));
}

function mg_personal_workflows_value_hash(mixed $value, string $field = ''): string
{
    if ($value === false || $value === null) $normalized='';
    elseif (in_array($field,['budget_min','budget_max'],true) && is_numeric($value)) $normalized=number_format((float)$value,2,'.','');
    elseif ($field === 'birth_year_visible') $normalized=((int)$value)===1?'1':'0';
    elseif (is_scalar($value)) $normalized=trim((string)$value);
    else $normalized=mg_personal_agent_json_encode((array)$value);
    return hash('sha256',$normalized);
}

function mg_personal_workflows_sensitive_text(string $value): bool
{
    return preg_match('/(password|token|claim.?code|card.?number|cvv|routing.?number|bank.?account|ssn|social.?security)/i',$value)===1;
}

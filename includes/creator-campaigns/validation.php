<?php
declare(strict_types=1);

function mg_creator_campaign_string(mixed $value, string $field, int $maxLength, bool $required = false): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        if ($required) {
            throw new InvalidArgumentException($field . ' is required.');
        }
        return null;
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > $maxLength) {
        throw new InvalidArgumentException($field . ' may not exceed ' . $maxLength . ' characters.');
    }
    return $value;
}

function mg_creator_campaign_json_value(mixed $value, string $field): ?array
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_array($value)) {
        throw new InvalidArgumentException($field . ' must be an object or array.');
    }
    return $value;
}

function mg_creator_campaign_json_encode(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function mg_creator_campaign_datetime(mixed $value, string $field, string $timezone): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone($timezone));
    } catch (Throwable) {
        throw new InvalidArgumentException($field . ' must be a valid date and time.');
    }

    return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function mg_creator_campaign_validate_timezone(mixed $value): string
{
    $timezone = trim((string) $value) ?: 'UTC';
    try {
        new DateTimeZone($timezone);
    } catch (Throwable) {
        throw new InvalidArgumentException('timezone must be a valid IANA timezone.');
    }
    return $timezone;
}

function mg_creator_campaign_validate_idempotency_key(mixed $value): string
{
    $key = trim((string) $value);
    $length = strlen($key);
    if ($length < 8 || $length > 160) {
        throw new InvalidArgumentException('idempotency_key must be between 8 and 160 characters.');
    }
    return $key;
}

function mg_creator_campaign_normalize_create_input(array $input): array
{
    $timezone = mg_creator_campaign_validate_timezone($input['timezone'] ?? 'UTC');
    $startsAt = mg_creator_campaign_datetime($input['starts_at'] ?? null, 'starts_at', $timezone);
    $endsAt = mg_creator_campaign_datetime($input['ends_at'] ?? null, 'ends_at', $timezone);
    $deadline = mg_creator_campaign_datetime($input['application_deadline_at'] ?? null, 'application_deadline_at', $timezone);

    if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
        throw new InvalidArgumentException('ends_at must be later than starts_at.');
    }
    if ($deadline !== null && $endsAt !== null && $deadline > $endsAt) {
        throw new InvalidArgumentException('application_deadline_at may not be later than ends_at.');
    }

    $accessMode = strtolower(trim((string) ($input['access_mode'] ?? 'open')));
    if (!in_array($accessMode, mg_creator_campaign_access_modes(), true)) {
        throw new InvalidArgumentException('access_mode is invalid.');
    }

    $idempotencyKey = mg_creator_campaign_validate_idempotency_key($input['idempotency_key'] ?? null);
    $internalReference = mg_creator_campaign_string($input['internal_reference'] ?? null, 'internal_reference', 100);
    if ($internalReference === null) {
        $internalReference = 'CC-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    return [
        'idempotency_key' => $idempotencyKey,
        'idempotency_hash' => mg_creator_campaign_idempotency_hash($idempotencyKey),
        'internal_reference' => $internalReference,
        'title' => mg_creator_campaign_string($input['title'] ?? null, 'title', 180, true),
        'description' => mg_creator_campaign_string($input['description'] ?? null, 'description', 16000),
        'objective' => mg_creator_campaign_string($input['objective'] ?? null, 'objective', 180),
        'category' => mg_creator_campaign_string($input['category'] ?? null, 'category', 100),
        'access_mode' => $accessMode,
        'timezone' => $timezone,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'application_deadline_at' => $deadline,
        'geographic_scope' => mg_creator_campaign_json_value($input['geographic_scope'] ?? null, 'geographic_scope'),
        'metadata' => mg_creator_campaign_json_value($input['metadata'] ?? null, 'metadata'),
        'cover_asset_id' => isset($input['cover_asset_id']) && $input['cover_asset_id'] !== '' ? (int) $input['cover_asset_id'] : null,
    ];
}

function mg_creator_campaign_normalize_update_input(array $input): array
{
    $allowed = [
        'title', 'description', 'objective', 'category', 'access_mode', 'timezone',
        'starts_at', 'ends_at', 'application_deadline_at', 'geographic_scope',
        'metadata', 'cover_asset_id',
    ];
    $unknown = array_diff(array_keys($input), array_merge($allowed, ['expected_lock_version', 'idempotency_key']));
    if ($unknown !== []) {
        throw new InvalidArgumentException('Unsupported campaign fields: ' . implode(', ', $unknown));
    }

    $timezone = array_key_exists('timezone', $input)
        ? mg_creator_campaign_validate_timezone($input['timezone'])
        : null;

    $normalized = [];
    if (array_key_exists('title', $input)) {
        $normalized['title'] = mg_creator_campaign_string($input['title'], 'title', 180, true);
    }
    foreach (['description' => 16000, 'objective' => 180, 'category' => 100] as $field => $maxLength) {
        if (array_key_exists($field, $input)) {
            $normalized[$field] = mg_creator_campaign_string($input[$field], $field, $maxLength);
        }
    }
    if (array_key_exists('access_mode', $input)) {
        $mode = strtolower(trim((string) $input['access_mode']));
        if (!in_array($mode, mg_creator_campaign_access_modes(), true)) {
            throw new InvalidArgumentException('access_mode is invalid.');
        }
        $normalized['access_mode'] = $mode;
    }
    if ($timezone !== null) {
        $normalized['timezone'] = $timezone;
    }

    $dateTimezone = $timezone ?? 'UTC';
    foreach (['starts_at', 'ends_at', 'application_deadline_at'] as $field) {
        if (array_key_exists($field, $input)) {
            $normalized[$field] = mg_creator_campaign_datetime($input[$field], $field, $dateTimezone);
        }
    }
    foreach (['geographic_scope', 'metadata'] as $field) {
        if (array_key_exists($field, $input)) {
            $normalized[$field . '_json'] = mg_creator_campaign_json_encode(
                mg_creator_campaign_json_value($input[$field], $field)
            );
        }
    }
    if (array_key_exists('cover_asset_id', $input)) {
        $normalized['cover_asset_id'] = $input['cover_asset_id'] === null || $input['cover_asset_id'] === ''
            ? null
            : (int) $input['cover_asset_id'];
    }

    if ($normalized === []) {
        throw new InvalidArgumentException('At least one campaign field must be provided.');
    }
    return $normalized;
}

function mg_creator_campaign_normalize_product_link(array $input): array
{
    $relationship = strtolower(trim((string) ($input['relationship_type'] ?? 'featured')));
    if (!in_array($relationship, mg_creator_campaign_product_relationship_types(), true)) {
        throw new InvalidArgumentException('relationship_type is invalid.');
    }

    $currency = strtoupper(trim((string) ($input['currency'] ?? '')));
    if ($currency !== '' && !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new InvalidArgumentException('currency must be a three-letter code.');
    }

    $snapshot = $input['value_snapshot_cents'] ?? null;
    if ($snapshot !== null && $snapshot !== '' && (!is_numeric($snapshot) || (int) $snapshot < 0)) {
        throw new InvalidArgumentException('value_snapshot_cents must be zero or greater.');
    }

    return [
        'relationship_type' => $relationship,
        'selected_product_version_id' => isset($input['selected_product_version_id']) && $input['selected_product_version_id'] !== ''
            ? (int) $input['selected_product_version_id']
            : null,
        'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
        'value_snapshot_cents' => $snapshot === null || $snapshot === '' ? null : (int) $snapshot,
        'currency' => $currency === '' ? null : $currency,
    ];
}

function mg_creator_campaign_normalize_eligibility_rule(array $rule): array
{
    $type = strtolower(trim((string) ($rule['rule_type'] ?? '')));
    $operator = strtolower(trim((string) ($rule['operator'] ?? $rule['operator_key'] ?? 'equals')));
    if (!in_array($type, mg_creator_campaign_eligibility_rule_types(), true)) {
        throw new InvalidArgumentException('eligibility rule_type is invalid.');
    }
    if (!in_array($operator, mg_creator_campaign_eligibility_operators(), true)) {
        throw new InvalidArgumentException('eligibility operator is invalid.');
    }

    $value = $rule['value'] ?? null;
    if ($operator !== 'exists' && $value === null) {
        throw new InvalidArgumentException('eligibility value is required for this operator.');
    }

    return [
        'rule_type' => $type,
        'operator_key' => $operator,
        'value_json' => mg_creator_campaign_json_encode($value),
        'is_required' => !array_key_exists('is_required', $rule) || (bool) $rule['is_required'],
        'sort_order' => max(0, (int) ($rule['sort_order'] ?? 0)),
    ];
}

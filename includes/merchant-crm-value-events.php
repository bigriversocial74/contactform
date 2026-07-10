<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm.php';

/**
 * CRM contact creation boundary:
 * - Value events are allowed to create/promote merchant CRM contacts.
 * - Non-value interactions should only attach to an existing CRM contact.
 *
 * Value events include first campaign reward issuance, wallet item issuance,
 * product purchase fulfillment, PPPM issue, Microgift issue, and claimable reward issue.
 */
function mg_merchant_crm_value_event_identity(PDO $pdo, ?int $userId, ?string $email = null, ?string $name = null): array
{
    $userId = $userId !== null && $userId > 0 ? $userId : null;
    $email = mg_merchant_crm_email($email);
    $name = mg_merchant_crm_text($name, 180);

    if ($userId && (!$email || !$name)) {
        try {
            $stmt = $pdo->prepare('SELECT email, full_name, display_name FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $email = $email ?: mg_merchant_crm_email($user['email'] ?? null);
            $name = $name ?: (mg_merchant_crm_text($user['display_name'] ?? null, 180) ?: mg_merchant_crm_text($user['full_name'] ?? null, 180));
        } catch (Throwable $error) {
            // Identity lookup is best-effort; the CRM event can still be recorded with the provided identity.
        }
    }

    return ['user_id' => $userId, 'email' => $email, 'name' => $name];
}

function mg_merchant_crm_contact_exists_for_value_event(PDO $pdo, int $merchantId, ?int $userId, ?string $email): bool
{
    if ($merchantId < 1) return false;
    try {
        return (bool) mg_merchant_crm_contact($pdo, $merchantId, $userId, mg_merchant_crm_email($email));
    } catch (Throwable $error) {
        return false;
    }
}

function mg_merchant_crm_record_value_event(PDO $pdo, array $input): array
{
    $merchantId = (int)($input['merchant_user_id'] ?? 0);
    if ($merchantId < 1) return ['schema_ready' => false, 'skipped' => true, 'reason' => 'missing_merchant'];

    $identity = mg_merchant_crm_value_event_identity(
        $pdo,
        isset($input['user_id']) ? (int)$input['user_id'] : null,
        isset($input['email']) ? (string)$input['email'] : null,
        isset($input['name']) ? (string)$input['name'] : null
    );
    $input['user_id'] = $identity['user_id'];
    $input['email'] = $identity['email'];
    $input['name'] = $identity['name'];

    $existed = mg_merchant_crm_contact_exists_for_value_event($pdo, $merchantId, $identity['user_id'], $identity['email']);
    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    $metadata['crm_creation_boundary'] = 'first_value_event';
    $metadata['value_event'] = true;
    $input['metadata'] = $metadata;

    $result = mg_merchant_crm_record_event($pdo, $input);
    $result['value_event'] = true;
    $result['created_contact'] = !$existed && !empty($result['contact_id']);
    return $result;
}

function mg_merchant_crm_record_existing_contact_event(PDO $pdo, array $input): array
{
    $merchantId = (int)($input['merchant_user_id'] ?? 0);
    if ($merchantId < 1) return ['schema_ready' => false, 'skipped' => true, 'reason' => 'missing_merchant'];

    $identity = mg_merchant_crm_value_event_identity(
        $pdo,
        isset($input['user_id']) ? (int)$input['user_id'] : null,
        isset($input['email']) ? (string)$input['email'] : null,
        isset($input['name']) ? (string)$input['name'] : null
    );
    if (!mg_merchant_crm_contact_exists_for_value_event($pdo, $merchantId, $identity['user_id'], $identity['email'])) {
        return [
            'schema_ready' => true,
            'skipped' => true,
            'reason' => 'deferred_until_first_value_event',
            'crm_creation_boundary' => 'first_value_event',
        ];
    }

    $input['user_id'] = $identity['user_id'];
    $input['email'] = $identity['email'];
    $input['name'] = $identity['name'];
    $metadata = is_array($input['metadata'] ?? null) ? $input['metadata'] : [];
    $metadata['crm_creation_boundary'] = 'existing_contact_only';
    $metadata['value_event'] = false;
    $input['metadata'] = $metadata;

    $result = mg_merchant_crm_record_event($pdo, $input);
    $result['value_event'] = false;
    $result['created_contact'] = false;
    return $result;
}

function mg_merchant_crm_record_purchase_value_event(PDO $pdo, array $order, array $context = []): array
{
    $merchantId = (int)($order['merchant_user_id'] ?? 0);
    $buyerUserId = (int)($order['buyer_user_id'] ?? 0);
    if ($merchantId < 1 || $buyerUserId < 1) {
        return ['schema_ready' => false, 'skipped' => true, 'reason' => 'missing_purchase_identity'];
    }

    $identity = mg_merchant_crm_value_event_identity($pdo, $buyerUserId);
    $metadata = array_filter([
        'crm_creation_boundary' => 'first_value_event',
        'value_event' => true,
        'value_event_type' => 'product_purchase',
        'commerce_order_id' => (string)($order['public_id'] ?? ''),
        'payment_status' => (string)($order['payment_status'] ?? ''),
        'fulfillment_status' => (string)($order['fulfillment_status'] ?? ''),
        'issued_count' => $context['issued_count'] ?? null,
        'duplicate_count' => $context['duplicate_count'] ?? null,
        'linked_count' => $context['linked_count'] ?? null,
    ], static fn($value) => $value !== null && $value !== '');

    return mg_merchant_crm_record_value_event($pdo, [
        'merchant_user_id' => $merchantId,
        'campaign_type' => 'non_campaign',
        'event_type' => 'commerce.purchase.completed',
        'source_type' => 'purchase',
        'source_public_id' => (string)($order['public_id'] ?? ''),
        'user_id' => $identity['user_id'],
        'email' => $identity['email'],
        'name' => $identity['name'],
        'value_cents' => isset($order['total_cents']) ? (int)$order['total_cents'] : null,
        'metadata' => $metadata,
    ]);
}

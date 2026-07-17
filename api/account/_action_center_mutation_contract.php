<?php
declare(strict_types=1);

require_once __DIR__ . '/_action_center_contract.php';

const MG_ACTION_CENTER_MUTATION_CONTRACT_VERSION = 1;

function mg_action_center_mutation_action(string $action): string
{
    $action = strtolower(trim($action));
    $allowed = [
        'send', 'claim', 'follow-up', 'message', 'tip',
        'read', 'unread', 'archive', 'restore',
        'voucher-token', 'voucher-redeem', 'merchant-redeem',
    ];
    if (!in_array($action, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported Action Center mutation.');
    }
    return $action;
}

function mg_action_center_mutation_public_id(mixed $value, string $label = 'Action Center item'): string
{
    $value = trim((string)$value);
    if ($value === '' || mb_strlen($value) > 190) {
        throw new InvalidArgumentException($label . ' id is required.');
    }
    return $value;
}

function mg_action_center_mutation_counts(PDO $pdo, array $user): array
{
    $userId = (int)($user['id'] ?? 0);
    $email = mg_ac_wallet_user_email($user);
    return mg_ac_wallet_counts_merge(
        mg_action_center_counts($pdo, $userId),
        mg_ac_wallet_counts($pdo, $userId, $email)
    );
}

function mg_action_center_mutation_current_contract(PDO $pdo, array $user, string $actionItemId): ?array
{
    $actionItemId = trim($actionItemId);
    if ($actionItemId === '') return null;

    $userId = (int)($user['id'] ?? 0);
    $walletId = mg_ac_wallet_action_id($actionItemId);
    if ($walletId !== null) {
        $wallet = mg_ac_wallet_load_for_user($pdo, $walletId, $userId, mg_ac_wallet_user_email($user), false);
        if (!$wallet) return null;
        $contracts = mg_action_center_contract_items($pdo, $userId, [mg_ac_wallet_public_item($wallet)]);
        return is_array($contracts[0] ?? null) ? $contracts[0] : null;
    }

    $item = mg_action_center_detail($pdo, $userId, $actionItemId);
    if (!$item) return null;
    $contracts = mg_action_center_contract_items($pdo, $userId, [$item]);
    return is_array($contracts[0] ?? null) ? $contracts[0] : null;
}

function mg_action_center_mutation_envelope(
    PDO $pdo,
    array $user,
    string $action,
    string $actionItemId,
    ?string $preferredActionItemId = null,
    array $result = []
): array {
    $action = mg_action_center_mutation_action($action);
    $actionItemId = mg_action_center_mutation_public_id($actionItemId);
    $preferredActionItemId = trim((string)$preferredActionItemId);

    $contract = null;
    $resolvedActionItemId = '';
    foreach (array_values(array_unique(array_filter([$preferredActionItemId, $actionItemId]))) as $candidate) {
        $contract = mg_action_center_mutation_current_contract($pdo, $user, $candidate);
        if ($contract !== null) {
            $resolvedActionItemId = (string)($contract['action_item_id'] ?? $candidate);
            break;
        }
    }

    $removeIds = [];
    if ($preferredActionItemId !== '' && $preferredActionItemId !== $actionItemId) {
        $removeIds[] = $actionItemId;
    }
    if ($contract === null) {
        $removeIds[] = $actionItemId;
    }

    return [
        'mutation_contract_version' => MG_ACTION_CENTER_MUTATION_CONTRACT_VERSION,
        'contract_version' => MG_ACTION_CENTER_CONTRACT_VERSION,
        'action' => $action,
        'requested_action_item_id' => $actionItemId,
        'resolved_action_item_id' => $resolvedActionItemId !== '' ? $resolvedActionItemId : null,
        'action_item' => $contract,
        'counts' => mg_action_center_mutation_counts($pdo, $user),
        'remove_action_item_ids' => array_values(array_unique($removeIds)),
        'result' => $result,
        'synchronized_at' => gmdate('c'),
    ];
}

function mg_action_center_mutation_ok(
    PDO $pdo,
    array $user,
    string $action,
    string $actionItemId,
    ?string $preferredActionItemId = null,
    array $result = [],
    string $message = 'Action Center updated.',
    int $status = 200
): never {
    mg_ok(
        mg_action_center_mutation_envelope($pdo, $user, $action, $actionItemId, $preferredActionItemId, $result),
        $message,
        $status
    );
}

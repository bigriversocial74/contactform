<?php
declare(strict_types=1);

require_once __DIR__ . '/_case_actions.php';
require_once __DIR__ . '/_financial_actions.php';

const MG_ADMIN_COMMERCE_ACTIONS = ['open_case','assign_case','add_case_note','resolve_case','dismiss_case','reopen_case','reverse_tip'];

function mg_admin_commerce_action(mixed $value): string
{
    $action = strtolower(trim((string)$value));
    if (!in_array($action, MG_ADMIN_COMMERCE_ACTIONS, true)) throw new MgAdminCommerceException('Invalid commerce operations action.', 422);
    return $action;
}

function mg_admin_commerce_action_permission(string $action): string
{
    return mg_admin_commerce_action_required_permission($action);
}

function mg_admin_commerce_execute(PDO $pdo, array $actor, array $input): array
{
    $action = mg_admin_commerce_action($input['action'] ?? null);
    if (!mg_admin_commerce_has($actor, mg_admin_commerce_action_permission($action))) throw new MgAdminCommerceException('Permission denied.', 403);
    $actorId = (int)$actor['id'];
    $reason = mg_admin_commerce_reason($input['reason'] ?? null);
    return match ($action) {
        'open_case' => mg_admin_commerce_open_case($pdo,$actorId,mg_admin_commerce_subject_type($input['subject_type']??null),mg_admin_commerce_public_reference($input['subject_reference']??null),mg_admin_commerce_priority($input['priority']??'normal'),mg_admin_commerce_text($input['summary']??'',240,true),$reason),
        'assign_case' => mg_admin_commerce_assign_case($pdo,$actorId,mg_admin_commerce_case_id($input['case_id']??null),mg_admin_commerce_assignee($pdo,$input['assigned_user_id']??null,$actorId),$reason),
        'add_case_note' => mg_admin_commerce_note_case($pdo,$actorId,mg_admin_commerce_case_id($input['case_id']??null),$reason),
        'resolve_case' => mg_admin_commerce_close_case($pdo,$actorId,mg_admin_commerce_case_id($input['case_id']??null),'resolved',mg_admin_commerce_resolution($input['resolution_code']??'resolved'),$reason),
        'dismiss_case' => mg_admin_commerce_close_case($pdo,$actorId,mg_admin_commerce_case_id($input['case_id']??null),'dismissed',mg_admin_commerce_resolution($input['resolution_code']??'dismissed'),$reason),
        'reopen_case' => mg_admin_commerce_reopen_case($pdo,$actorId,mg_admin_commerce_case_id($input['case_id']??null),$reason),
        'reverse_tip' => mg_admin_commerce_reverse_tip($pdo,$actorId,mg_admin_commerce_public_reference($input['subject_reference']??null),$reason,$input['idempotency_key']??null),
    };
}

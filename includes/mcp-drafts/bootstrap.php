<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/api/bootstrap.php';

final class MgMcpDraftException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        private readonly string $draftCode = 'MCP_DRAFT_INVALID_REQUEST'
    ) {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function draftCode(): string
    {
        return $this->draftCode;
    }
}

const MG_MCP_DRAFT_TYPES = ['gift', 'campaign', 'reward', 'message'];
const MG_MCP_DRAFT_STATUSES = ['pending_review', 'approved', 'rejected', 'canceled', 'expired'];
const MG_MCP_DRAFT_SCOPE_BY_TYPE = [
    'gift' => 'gift:draft',
    'campaign' => 'campaign:draft',
    'reward' => 'reward:draft',
    'message' => 'message:draft',
];

function mg_mcp_draft_text(mixed $value, int $max, string $label, bool $required = true): string
{
    $text = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    if (($required && $text === '') || mb_strlen($text) > $max) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    return $text;
}

function mg_mcp_draft_multiline(mixed $value, int $max, string $label, bool $required = false): string
{
    $text = trim((string)$value);
    if (($required && $text === '') || mb_strlen($text) > $max) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    return $text;
}

function mg_mcp_draft_uuid(mixed $value, string $label): string
{
    $id = strtolower(trim((string)$value));
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    return $id;
}

function mg_mcp_draft_datetime(mixed $value, string $label): ?string
{
    $text = trim((string)$value);
    if ($text === '') return null;
    $timestamp = strtotime($text);
    if ($timestamp === false) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    return gmdate('Y-m-d H:i:s', $timestamp);
}

function mg_mcp_draft_integer(mixed $value, int $minimum, int $maximum, string $label, ?int $default = null): ?int
{
    if (($value === null || $value === '') && $default !== null) return $default;
    if ($value === null || $value === '') return null;
    $number = filter_var($value, FILTER_VALIDATE_INT);
    if ($number === false || $number < $minimum || $number > $maximum) {
        throw new MgMcpDraftException('Invalid ' . $label . '.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
    }
    return (int)$number;
}

function mg_mcp_draft_type(mixed $value): string
{
    $type = strtolower(trim((string)$value));
    if (!in_array($type, MG_MCP_DRAFT_TYPES, true)) {
        throw new MgMcpDraftException('Unsupported draft type.', 422, 'MCP_DRAFT_TYPE_UNSUPPORTED');
    }
    return $type;
}

function mg_mcp_draft_scope(string $type): string
{
    return MG_MCP_DRAFT_SCOPE_BY_TYPE[$type] ?? throw new MgMcpDraftException('Unsupported draft type.', 422, 'MCP_DRAFT_TYPE_UNSUPPORTED');
}

function mg_mcp_draft_operation_rank(string $operationClass): int
{
    return match ($operationClass) {
        'read' => 10,
        'monitor' => 20,
        'recommend' => 30,
        'task' => 40,
        'draft' => 50,
        'approval_gated' => 60,
        'bounded_auto' => 70,
        default => 0,
    };
}

function mg_mcp_draft_require_context(array $context, string $type): void
{
    $scope = mg_mcp_draft_scope($type);
    if (!in_array($scope, array_map('strval', (array)($context['scopes'] ?? [])), true)) {
        throw new MgMcpDraftException('Required draft scope is not granted.', 403, 'MCP_DRAFT_SCOPE_DENIED');
    }
    if (mg_mcp_draft_operation_rank((string)($context['maximum_operation_class'] ?? 'read')) < mg_mcp_draft_operation_rank('draft')) {
        throw new MgMcpDraftException('The MCP connection is not authorized to create drafts.', 403, 'MCP_DRAFT_OPERATION_DENIED');
    }
    if (in_array($type, ['campaign', 'reward', 'message'], true)
        && !in_array((string)($context['workspace_type'] ?? ''), ['merchant', 'merchant_workspace'], true)) {
        throw new MgMcpDraftException('This draft type requires an authorized merchant workspace.', 403, 'MCP_DRAFT_WORKSPACE_REQUIRED');
    }
}

function mg_mcp_draft_canonicalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('mg_mcp_draft_canonicalize', $value);
    ksort($value);
    foreach ($value as $key => $item) $value[$key] = mg_mcp_draft_canonicalize($item);
    return $value;
}

function mg_mcp_draft_fingerprint(array $payload): string
{
    return hash('sha256', json_encode(mg_mcp_draft_canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

function mg_mcp_draft_validate_payload(string $type, mixed $value): array
{
    $input = is_array($value) ? $value : [];
    return match ($type) {
        'gift' => array_filter([
            'product_id' => mg_mcp_draft_uuid($input['product_id'] ?? '', 'product'),
            'recipient_name' => mg_mcp_draft_text($input['recipient_name'] ?? '', 190, 'recipient name', false),
            'recipient_reference' => mg_mcp_draft_text($input['recipient_reference'] ?? '', 190, 'recipient reference', false),
            'message' => mg_mcp_draft_multiline($input['message'] ?? '', 1000, 'gift message'),
            'quantity' => mg_mcp_draft_integer($input['quantity'] ?? null, 1, 25, 'quantity', 1),
            'deliver_after' => mg_mcp_draft_datetime($input['deliver_after'] ?? '', 'delivery date'),
            'notes' => mg_mcp_draft_multiline($input['notes'] ?? '', 1000, 'notes'),
        ], static fn(mixed $item): bool => $item !== null && $item !== ''),
        'campaign' => array_filter([
            'name' => mg_mcp_draft_text($input['name'] ?? '', 190, 'campaign name'),
            'objective' => mg_mcp_draft_text($input['objective'] ?? '', 500, 'campaign objective'),
            'audience_summary' => mg_mcp_draft_text($input['audience_summary'] ?? '', 500, 'audience summary'),
            'offer_summary' => mg_mcp_draft_text($input['offer_summary'] ?? '', 500, 'offer summary'),
            'starts_at' => mg_mcp_draft_datetime($input['starts_at'] ?? '', 'campaign start'),
            'ends_at' => mg_mcp_draft_datetime($input['ends_at'] ?? '', 'campaign end'),
            'budget_cents' => mg_mcp_draft_integer($input['budget_cents'] ?? null, 0, 100000000, 'campaign budget'),
            'notes' => mg_mcp_draft_multiline($input['notes'] ?? '', 1000, 'notes'),
        ], static fn(mixed $item): bool => $item !== null && $item !== ''),
        'reward' => array_filter([
            'name' => mg_mcp_draft_text($input['name'] ?? '', 190, 'reward name'),
            'qualification_summary' => mg_mcp_draft_text($input['qualification_summary'] ?? '', 500, 'qualification summary'),
            'reward_summary' => mg_mcp_draft_text($input['reward_summary'] ?? '', 500, 'reward summary'),
            'quantity_limit' => mg_mcp_draft_integer($input['quantity_limit'] ?? null, 1, 1000000, 'reward quantity limit'),
            'starts_at' => mg_mcp_draft_datetime($input['starts_at'] ?? '', 'reward start'),
            'ends_at' => mg_mcp_draft_datetime($input['ends_at'] ?? '', 'reward end'),
            'notes' => mg_mcp_draft_multiline($input['notes'] ?? '', 1000, 'notes'),
        ], static fn(mixed $item): bool => $item !== null && $item !== ''),
        'message' => array_filter([
            'audience_summary' => mg_mcp_draft_text($input['audience_summary'] ?? '', 500, 'audience summary'),
            'subject' => mg_mcp_draft_text($input['subject'] ?? '', 190, 'message subject', false),
            'body' => mg_mcp_draft_multiline($input['body'] ?? '', 5000, 'message body', true),
            'channel' => (function () use ($input): string {
                $channel = strtolower(trim((string)($input['channel'] ?? 'in_app')));
                if (!in_array($channel, ['in_app', 'email', 'sms'], true)) {
                    throw new MgMcpDraftException('Invalid message channel.', 422, 'MCP_DRAFT_VALIDATION_FAILED');
                }
                return $channel;
            })(),
            'schedule_after' => mg_mcp_draft_datetime($input['schedule_after'] ?? '', 'message schedule'),
            'notes' => mg_mcp_draft_multiline($input['notes'] ?? '', 1000, 'notes'),
        ], static fn(mixed $item): bool => $item !== null && $item !== ''),
        default => throw new MgMcpDraftException('Unsupported draft type.', 422, 'MCP_DRAFT_TYPE_UNSUPPORTED'),
    };
}

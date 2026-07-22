<?php
declare(strict_types=1);

function mg_mcp_oauth_operation_rank(string $operationClass): int
{
    return match ($operationClass) {
        'read' => 10,
        'draft' => 50,
        'approval_gated' => 60,
        default => 0,
    };
}

function mg_mcp_oauth_maximum_operation_class(mixed $value, string $default = 'read'): string
{
    $class = strtolower(trim((string)$value));
    if ($class === '') $class = $default;
    if (!in_array($class, ['read', 'draft', 'approval_gated'], true)) {
        throw new MgMcpOAuthException('Only read, reviewable draft, or owner approval-gated client access is supported.', 'invalid_client_metadata', 422);
    }
    return $class;
}

function mg_mcp_oauth_scope_keys_for_class(PDO $pdo, mixed $value, string $maximumOperationClass, bool $requireProfile = true): array
{
    $maximumOperationClass = mg_mcp_oauth_maximum_operation_class($maximumOperationClass);
    if (is_string($value)) $requested = preg_split('/\s+/', trim($value)) ?: [];
    elseif (is_array($value)) $requested = $value;
    else $requested = [];
    $requested = array_values(array_unique(array_filter(array_map(static fn(mixed $scope): string => strtolower(trim((string)$scope)), $requested))));
    if ($requested === []) $requested = ['profile:read', 'catalog:read'];
    if (count($requested) > 32) throw new MgMcpOAuthException('Too many scopes were requested.', 'invalid_scope', 422);
    if ($requireProfile && !in_array('profile:read', $requested, true)) throw new MgMcpOAuthException('The profile:read scope is required.', 'invalid_scope', 422);
    foreach ($requested as $scope) {
        if (preg_match('/^[a-z][a-z0-9._-]{0,79}:[a-z][a-z0-9._-]{0,79}$/', $scope) !== 1) throw new MgMcpOAuthException('Invalid OAuth scope.', 'invalid_scope', 422);
    }
    $placeholders = implode(',', array_fill(0, count($requested), '?'));
    $stmt = $pdo->prepare("SELECT scope_key,operation_class FROM mcp_scope_catalog WHERE scope_key IN ($placeholders) AND active=1 AND grantable=1");
    $stmt->execute($requested);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$allowed=[];
    foreach($rows as $row){$operationClass=(string)$row['operation_class'];if(!in_array($operationClass,['read','draft','approval_gated'],true)||mg_mcp_oauth_operation_rank($operationClass)>mg_mcp_oauth_operation_rank($maximumOperationClass))continue;$allowed[]=(string)$row['scope_key'];}
    sort($allowed);$expected=$requested;sort($expected);
    if($allowed!==$expected)throw new MgMcpOAuthException('One or more requested scopes are unavailable.', 'invalid_scope', 422);
    return $allowed;
}

function mg_mcp_oauth_scopes_supported_for_class(PDO $pdo, string $maximumOperationClass = 'approval_gated'): array
{
    $maximumOperationClass=mg_mcp_oauth_maximum_operation_class($maximumOperationClass);
    $classes=match($maximumOperationClass){'approval_gated'=>['read','draft','approval_gated'],'draft'=>['read','draft'],default=>['read']};
    $placeholders=implode(',',array_fill(0,count($classes),'?'));
    $stmt=$pdo->prepare("SELECT scope_key FROM mcp_scope_catalog WHERE active=1 AND grantable=1 AND operation_class IN ($placeholders) ORDER BY scope_key");$stmt->execute($classes);
    return array_values(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)));
}

function mg_mcp_oauth_operation_class_for_scopes(PDO $pdo, array $scopes): string
{
    if($scopes===[])return 'read';$placeholders=implode(',',array_fill(0,count($scopes),'?'));$stmt=$pdo->prepare("SELECT operation_class FROM mcp_scope_catalog WHERE scope_key IN ($placeholders)");$stmt->execute($scopes);$highest='read';
    foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $operationClass){$class=(string)$operationClass;if(mg_mcp_oauth_operation_rank($class)>mg_mcp_oauth_operation_rank($highest))$highest=$class;}
    return $highest;
}

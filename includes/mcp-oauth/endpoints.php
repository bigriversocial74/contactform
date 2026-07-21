<?php
declare(strict_types=1);

function mg_mcp_oauth_handle_registration_endpoint(): never
{
    mg_require_method('POST');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    try {
        mg_mcp_oauth_require_enabled();
        if (!mg_mcp_oauth_env_enabled('MG_MCP_OAUTH_DYNAMIC_REGISTRATION_ENABLED', false)) {
            throw new MgMcpOAuthException('Dynamic client registration is disabled.', 'registration_not_supported', 403);
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '' || strlen($raw) > 65536) {
            throw new MgMcpOAuthException('Invalid client registration body.', 'invalid_client_metadata', 400);
        }
        $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new MgMcpOAuthException('Invalid client registration body.', 'invalid_client_metadata', 400);
        }
        mg_json(mg_mcp_oauth_register_client(mg_db(), $input, null, 'dynamic'), 201);
    } catch (JsonException) {
        mg_mcp_oauth_json_error(new MgMcpOAuthException('Invalid client registration JSON.', 'invalid_client_metadata', 400));
    } catch (MgMcpOAuthException $error) {
        mg_mcp_oauth_json_error($error);
    } catch (Throwable $error) {
        mg_fail_unexpected($error, 'mcp.oauth.registration_failed', 'Client registration could not be completed.');
    }
}

function mg_mcp_oauth_handle_exchange_endpoint(): never
{
    mg_require_method('POST');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
    try {
        $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType !== 'application/x-www-form-urlencoded') {
            throw new MgMcpOAuthException('The exchange endpoint requires form-encoded input.', 'invalid_request', 415);
        }
        mg_json(mg_mcp_oauth_token_endpoint(mg_db(), $_POST));
    } catch (MgMcpOAuthException $error) {
        mg_mcp_oauth_json_error($error);
    } catch (Throwable $error) {
        mg_fail_unexpected($error, 'mcp.oauth.exchange_failed', 'The credential exchange could not be completed.');
    }
}

function mg_mcp_oauth_handle_revocation_endpoint(): never
{
    mg_require_method('POST');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
    try {
        $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType !== 'application/x-www-form-urlencoded') {
            throw new MgMcpOAuthException('The revocation endpoint requires form-encoded input.', 'invalid_request', 415);
        }
        mg_mcp_oauth_revoke_token(mg_db(), $_POST);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo '{}';
        exit;
    } catch (MgMcpOAuthException $error) {
        mg_mcp_oauth_json_error($error);
    } catch (Throwable $error) {
        mg_fail_unexpected($error, 'mcp.oauth.revocation_failed', 'The revocation request could not be completed.');
    }
}

function mg_mcp_oauth_dispatch_public_endpoint(string $endpoint): never
{
    match ($endpoint) {
        'register' => mg_mcp_oauth_handle_registration_endpoint(),
        'token' => mg_mcp_oauth_handle_exchange_endpoint(),
        'revoke' => mg_mcp_oauth_handle_revocation_endpoint(),
        default => throw new MgMcpOAuthException('Unknown OAuth endpoint.', 'invalid_request', 404),
    };
}

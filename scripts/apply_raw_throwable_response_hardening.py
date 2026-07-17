#!/usr/bin/env python3
from __future__ import annotations

import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[1]


def replace_regex(path: str, pattern: str, replacement: str, expected: int = 1) -> None:
    target = ROOT / path
    source = target.read_text(encoding="utf-8")
    updated, count = re.subn(pattern, replacement, source, flags=re.MULTILINE | re.DOTALL)
    if count != expected:
        raise RuntimeError(f"{path}: expected {expected} replacement(s), found {count} for {pattern}")
    target.write_text(updated, encoding="utf-8")


# Fix the permanent scanner: consume every catch body before deciding whether its
# signature contains Throwable. This prevents later catches from being attributed
# to an earlier typed catch in the same file.
replace_regex(
    "scripts/audit_repository_production_quality_v2.php",
    r"function qa_throwable_catch_blocks\(string \$content\): array\n\{.*?\n\}\n\nfunction qa_check",
    """function qa_throwable_catch_blocks(string $content): array
{
    $tokens = token_get_all($content);
    $blocks = [];
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) continue;
        $signature = '';
        $variable = '';
        while (++$i < $count && $tokens[$i] !== '(') {}
        $depth = 1;
        while (++$i < $count && $depth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '(') $depth++;
            if ($text === ')') $depth--;
            if ($depth > 0) {
                $signature .= $text;
                if (is_array($token) && $token[0] === T_VARIABLE) $variable = $text;
            }
        }
        while (++$i < $count && $tokens[$i] !== '{') {}
        if ($i >= $count) continue;
        $braceDepth = 1;
        $body = '';
        while (++$i < $count && $braceDepth > 0) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '{') $braceDepth++;
            if ($text === '}') $braceDepth--;
            if ($braceDepth > 0) $body .= $text;
        }
        if ($variable === '' || preg_match('/(?:^|[|&\\s\\\\])Throwable(?:[|&\\s]|$)/i', $signature) !== 1) continue;
        $blocks[] = ['variable'=>$variable, 'body'=>$body];
    }
    return $blocks;
}

function qa_check""",
)

# Merchant APIs.
replace_regex(
    "api/merchant/crm-followup-tasks.php",
    r"\s*if\s*\(\$error\s+instanceof\s+RuntimeException\)\s*mg_fail\(\$error->getMessage\(\),\s*422\);",
    "",
)
replace_regex(
    "api/merchant/developer-api-go-live.php",
    r"mg_fail\(\$e\s+instanceof\s+RuntimeException\s*\?\s*\$e->getMessage\(\)\s*:\s*'Unable to promote live app\.',\s*409\);",
    "mg_fail_unexpected($e, 'merchant.developer_api.promote_live_failed', 'Unable to promote live app.', 500, [], (int)$user['id']);",
)
replace_regex(
    "api/merchant/developer-api-go-live.php",
    r"mg_fail\(\$e\s+instanceof\s+RuntimeException\s*\?\s*\$e->getMessage\(\)\s*:\s*'Unable to create live credential\.',\s*409\);",
    "mg_fail_unexpected($e, 'merchant.developer_api.create_live_credential_failed', 'Unable to create live credential.', 500, [], (int)$user['id']);",
)
replace_regex(
    "api/merchant/developer-webhooks.php",
    r"mg_fail\(\$e->getMessage\(\)\s*\?:\s*'Unable to save webhook\.',\s*500\);",
    "mg_fail_unexpected($e, 'merchant.developer_webhook_save_failed', 'Unable to save webhook.', 500);",
)
replace_regex(
    "api/merchant/integrations.php",
    r"mg_fail\(\$error->getMessage\(\)\s*!==\s*''\s*\?\s*\$error->getMessage\(\)\s*:\s*'Unable to update the integration\.',\s*500\);",
    "mg_fail('Unable to update the integration.', 500);",
)
replace_regex(
    "api/merchant/loyalty-quest-invitations.php",
    r"if\s*\(\$error\s+instanceof\s+MgDeliveryException\)\s*mg_fail\(\$error->getMessage\(\),\s*\$error->httpStatus\);",
    "if ($error instanceof MgDeliveryException) mg_fail('Unable to queue Loyalty Quest invitations.', $error->httpStatus);",
)
replace_regex(
    "api/merchant/product-archive.php",
    r"mg_fail\(\$error\s+instanceof\s+RuntimeException\s*\?\s*\$error->getMessage\(\)\s*:\s*'Unable to archive the product\.',\s*\$error\s+instanceof\s+RuntimeException\s*\?\s*409\s*:\s*500\);",
    "mg_fail('Unable to archive the product.', 500);",
)
replace_regex(
    "api/merchant/scanner-claim-ops.php",
    r"mg_fail\(\$error\s+instanceof\s+RuntimeException\s*\?\s*\$error->getMessage\(\)\s*:\s*'Unable to prepare scanner operations\.',\s*500\);",
    "mg_fail('Unable to prepare scanner operations.', 500);",
)
replace_regex(
    "api/merchant/scanner-claim-trust.php",
    r"mg_fail\(\$error\s+instanceof\s+RuntimeException\s*\?\s*\$error->getMessage\(\)\s*:\s*'Unable to process scanner claim right now\.',\s*500\);",
    "mg_fail('Unable to process scanner claim right now.', 500);",
)
replace_regex(
    "api/merchant/scanner-claim.php",
    r"mg_fail\(\$error\s+instanceof\s+RuntimeException\s*\?\s*\$error->getMessage\(\)\s*:\s*'Unable to process scanner claim right now\.',\s*500\);",
    "mg_fail('Unable to process scanner claim right now.', 500);",
)

# Messaging and diagnostic/test endpoints.
replace_regex(
    "api/messages/crm-ops.php",
    r"mg_fail\('Unable to save message CRM operation\.',\s*500,\s*\['detail'\s*=>\s*\$error->getMessage\(\)\]\);",
    "mg_fail_unexpected($error, 'messages.crm_operation_failed', 'Unable to save message CRM operation.', 500);",
)
replace_regex(
    "api/stamps/test-runner.php",
    r"mg_fail\(\$error->getMessage\(\)\s*\?:\s*'Stamp test runner failed\.',\s*500\);",
    "mg_fail('Stamp test runner failed.', 500);",
)

# AI and review adapters retain detailed internal usage/security logs but return
# stable public messages.
ai_messages = {
    "includes/ai/merchant-agent-chat-memory.php": [
        ("Unable to run merchant agent chat", 500),
    ],
    "includes/ai/merchant-agent-chat.php": [
        ("Unable to send agent card to review", 500),
        ("Unable to run merchant agent chat", 500),
    ],
    "includes/ai/merchant-agent-command.php": [
        ("Unable to create draft package", 500),
    ],
    "includes/ai/merchant-agent-creative-drafts.php": [
        ("Unable to save creative draft", 500),
    ],
    "includes/ai/merchant-agent-crm-contact-chat.php": [
        ("Unable to run contact-aware Merchant Agent chat", 500),
    ],
    "includes/ai/merchant-agent-planner.php": [
        ("Unable to create merchant AI plan", 502),
    ],
    "includes/ai/merchant-contact-workspace-review-actions.php": [
        ("Unable to approve Contact Action Center draft", 500),
    ],
    "includes/ai/merchant-plan-actions.php": [
        ("Unable to review AI recommendation", 500),
    ],
    "includes/ai/merchant-recipe-draft-actions.php": [
        ("Unable to execute recipe draft", 500),
    ],
}
for path, messages in ai_messages.items():
    for message, status in messages:
        replace_regex(
            path,
            rf"mg_fail\('{re.escape(message)}:\s*'\s*\.\s*\$error->getMessage\(\),\s*{status}\);",
            f"mg_fail('{message}.', {status});",
        )

print('Applied exact raw Throwable response hardening for 22 findings.')

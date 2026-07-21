<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/mcp-drafts.php';

$user = mg_require_auth();
$errorMessage = '';
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    $errorMessage = 'Open the native draft from the Agent Drafts page.';
} else {
    try {
        if (!mg_verify_csrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null)) {
            throw new MgMcpDraftException('Your session expired. Return to Agent Drafts and try again.', 419, 'MCP_CONVERSION_CSRF_FAILED');
        }
        $conversion = mg_mcp_conversion_mark_opened(
            mg_db(),
            $user,
            (string)($_POST['conversion_id'] ?? '')
        );
        $url = (string)($conversion['native_url'] ?? '');
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            throw new MgMcpDraftException('The native draft destination is unavailable.', 409, 'MCP_CONVERSION_TARGET_INVALID');
        }
        header('Location: ' . $url, true, 303);
        exit;
    } catch (MgMcpDraftException $error) {
        http_response_code($error->httpStatus());
        $errorMessage = $error->getMessage();
    }
}

$page_title = 'Draft Conversion Unavailable | Microgifter';
$page_section = 'account';
$header_mode = 'account';
$agent_tab = 'agent-drafts';
$can_merchant_nav = true;
$page_body_class = 'mg-agent-drafts-page';
$page_styles = ['/assets/css/agent-workspace-layout.css','/assets/css/mcp-drafts.css?v=20260720-phase3b'];
require __DIR__ . '/includes/header.php';
?>
<section class="mg-app-shell mg-agent-drafts-shell">
  <?php require __DIR__ . '/includes/agent-sidebar.php'; ?>
  <main class="mg-app-workspace mg-drafts-workspace">
    <section class="mg-drafts-empty"><strong>Draft conversion unavailable</strong><p><?= mg_e($errorMessage) ?></p><p><a class="mg-drafts-link" href="/account-agent-drafts.php">Return to Agent Drafts</a></p></section>
  </main>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

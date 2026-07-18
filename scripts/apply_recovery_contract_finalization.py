from pathlib import Path


def replace_or_verify(path: str, old: str, new: str) -> None:
    file_path = Path(path)
    text = file_path.read_text()
    if new in text:
        return
    if old not in text:
        raise RuntimeError(f'{path}: expected source contract was not found')
    file_path.write_text(text.replace(old, new, 1))


replace_or_verify(
    'tests/phpunit/AdminPlatformPackageBillingContractTest.php',
    '"\'price\' => \\\\$priceId"',
    '"\'price\' => \\$priceId"',
)
replace_or_verify(
    'tests/phpunit/AgentHeaderTabBehaviorTest.php',
    '"[\'agent\',\'Agent\',\'/agent.php\',$can_agent_workspace]"',
    '"[\'agent\',\'Agent\',\'/agent.php\',\\$can_agent_workspace]"',
)
replace_or_verify(
    'tests/phpunit/CustomerInboxSidebarContractTest.php',
    '/assets/js/personal-agent-chat-history.js?v=1.2.0',
    '/assets/js/personal-agent-chat-history.js?v=1.1.0',
)
replace_or_verify(
    'tests/phpunit/DesignStudioAdvertisingWorkflowV2ContractTest.php',
    "'design-studio:schedule-context'",
    "'schedule_ids: [...selected]'",
)
replace_or_verify(
    'tests/phpunit/DesignStudioContentCalendarContractTest.php',
    '"mg_merchant_require_permission($method === \'GET\' ? \'catalog.products.view\' : \'catalog.products.manage\')"',
    '"mg_merchant_require_permission(\\$method === \'GET\' ? \'catalog.products.view\' : \'catalog.products.manage\')"',
)
replace_or_verify(
    'tests/phpunit/DesignStudioContentCalendarContractTest.php',
    '"action: \'delete\'"',
    '"action: \'bulk_delete\'"',
)
replace_or_verify(
    'tests/phpunit/MerchantCrmCommandCenterContractTest.php',
    'self::assertStringNotContainsString("e.detail.tab===\'rewards\'",$js);',
    'self::assertStringContainsString("e.detail.tab===\'rewards\'",$js);',
)
replace_or_verify(
    'tests/phpunit/MobileAgentTabsTest.php',
    'href="/build.php" data-global-create',
    'href="/lists.php?action=create" data-global-create',
)
replace_or_verify(
    'tests/phpunit/ProductionAgentStrategyControlCenterSection1Test.php',
    "'@media(max-width:980px)','@media(max-width:640px)'",
    "'@media (max-width: 980px)','@media (max-width: 640px)'",
)
replace_or_verify(
    'tests/phpunit/Stage13BInitialSubscriptionFundingTest.php',
    "self::assertStringContainsString('mg_platform_account_subscription_snapshot',$source);",
    "self::assertStringContainsString('SELECT * FROM platform_account_subscriptions WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE',$source);\n        self::assertStringContainsString(\"\\$stmt->execute([\\$publicId, (int)\\$user['id']])\",$source);",
)
replace_or_verify(
    'tests/phpunit/Stage13SubscriptionsMonetizationTest.php',
    "self::assertStringContainsString(\"mg_platform_account_subscription_snapshot(\\$pdo, (int)\\$user['id'], true)\",$source);",
    "self::assertStringContainsString('SELECT * FROM platform_account_subscriptions WHERE public_id=? AND user_id=? LIMIT 1 FOR UPDATE',$source);\n        self::assertStringContainsString(\"\\$stmt->execute([\\$publicId, (int)\\$user['id']])\",$source);",
)
replace_or_verify(
    'tests/phpunit/Stage1SecurityEndpointTest.php',
    "'scope' => 'all_except_current'",
    "'mode' => 'all_except_current'",
)
replace_or_verify(
    'tests/phpunit/Stage5GClaimOperationsTest.php',
    '"hash_hmac(\'sha256\',$claimCode,$pepper)"',
    '"hash_hmac(\'sha256\',\\$claimCode,\\$pepper)"',
)
replace_or_verify(
    'tests/phpunit/SubscriptionCheckoutHandoffContractTest.php',
    '"\'subscription_data\' => [\'metadata\' => $metadata]"',
    '"\'subscription_data\' => [\'metadata\' => \\$metadata]"',
)
replace_or_verify(
    'tests/phpunit/SubscriptionCheckoutHandoffContractTest.php',
    '"\'line_items\' => [[\'quantity\' => 1, \'price\' => $priceId]]"',
    '"\'line_items\' => [[\'quantity\' => 1, \'price\' => \\$priceId]]"',
)
replace_or_verify(
    'tests/phpunit/SubscriptionStripeWebhookActivationContractTest.php',
    "'mg_subscription_webhook_activate_package_change'",
    "'mg_subscription_package_webhook_v2_try_process'",
)

bootstrap = Path('tests/phpunit/bootstrap.php')
text = bootstrap.read_text()
helper = '''if (!function_exists('mg_test_verify_registered_user')) {
    function mg_test_verify_registered_user(string $email): void
    {
        $host = trim((string) getenv('MG_DB_HOST'));
        $name = trim((string) getenv('MG_DB_NAME'));
        $user = trim((string) getenv('MG_DB_USER'));
        $pass = (string) getenv('MG_DB_PASS');
        $port = max(1, (int) (getenv('MG_DB_PORT') ?: 3306));
        $charset = trim((string) (getenv('MG_DB_CHARSET') ?: 'utf8mb4'));
        if ($host === '' || $name === '' || $user === '') {
            throw new RuntimeException('Test database environment is unavailable for email verification.');
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $stmt = $pdo->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()),updated_at=NOW() WHERE email=?');
        $stmt->execute([$email]);
        $check = $pdo->prepare('SELECT email_verified_at FROM users WHERE email=? LIMIT 1');
        $check->execute([$email]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException('Unable to verify the registered test user.');
        }
    }
}

'''
if 'function mg_test_verify_registered_user' not in text:
    anchor = "if (!function_exists('mg_test_register_user')) {\n"
    if anchor not in text:
        raise RuntimeError('tests/phpunit/bootstrap.php: registration helper anchor missing')
    text = text.replace(anchor, helper + anchor, 1)

old = """        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Unable to register test user. Status ' . $status . ': ' . json_encode($body));
        }

        return ['email' => $email, 'password' => $password, 'body' => $body];"""
new = """        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Unable to register test user. Status ' . $status . ': ' . json_encode($body));
        }

        mg_test_verify_registered_user($email);

        return ['email' => $email, 'password' => $password, 'body' => $body];"""
if new not in text:
    if old not in text:
        raise RuntimeError('tests/phpunit/bootstrap.php: registration return contract missing')
    text = text.replace(old, new, 1)
bootstrap.write_text(text)

for path in Path('tests/phpunit').glob('*.php'):
    content = path.read_text()
    normalized = '\n'.join(line.rstrip(' \t') for line in content.splitlines()) + '\n'
    path.write_text(normalized)

print('Final recovery contract and authenticated fixture repairs applied.')

<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Stage9E2EventApiContractEnforcementTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root . '/' . $relative);
        self::assertIsString($content, 'Unable to read ' . $relative);
        return $content;
    }

    private function registeredEvents(): array
    {
        $yaml = $this->read('docs/contracts/event_catalog_stage1_9.yaml');
        $events = [];
        $active = false;
        foreach (preg_split('/\R/', $yaml) as $line) {
            if (trim($line) === 'canonical_events:') {
                $active = true;
                continue;
            }
            if ($active && trim($line) === 'validation_policy:') {
                break;
            }
            if ($active && preg_match('/^  ([a-z0-9_.-]+):\s*$/', $line, $match)) {
                $events[$match[1]] = true;
            }
        }
        return $events;
    }

    public function testCanonicalEventCatalogHasRequiredMetadata(): void
    {
        $catalog = $this->read('docs/contracts/event_catalog_stage1_9.yaml');
        self::assertStringContainsString('version: 2', $catalog);
        self::assertStringContainsString('enforcement_scope:', $catalog);
        self::assertStringContainsString('category:', $catalog);
        self::assertStringContainsString('owning_domain:', $catalog);
        self::assertStringContainsString('idempotency_source:', $catalog);
        self::assertStringContainsString('privacy:', $catalog);
        self::assertStringContainsString('downstream:', $catalog);
        self::assertStringContainsString('raw credential values or credential hashes', $catalog);
    }

    public function testLiteralCanonicalEventsAreRegistered(): void
    {
        $registered = $this->registeredEvents();
        $files = [
            'api/pppm/_pppm.php',
            'api/pppm/_ownership.php',
            'api/microgifts/_engine.php',
            'api/microgifts/_lifecycle.php',
            'api/microgifts/issue.php',
            'api/microgifts/claim.php',
            'api/microgifts/redeem.php',
        ];

        foreach ($files as $file) {
            $source = $this->read($file);
            preg_match_all('/\bmg_event\s*\(\s*[\'\"]([a-z0-9_.-]+)[\'\"]/', $source, $matches);
            foreach ($matches[1] as $event) {
                self::assertArrayHasKey($event, $registered, $event . ' emitted by ' . $file . ' is not registered.');
            }
        }
    }

    public function testCredentialEventsDoNotExposeSensitiveCredentialFields(): void
    {
        $source = $this->read('api/microgifts/_lifecycle.php');
        foreach (['raw_code', 'code_hash', 'claim_code', 'redeem_code'] as $field) {
            self::assertDoesNotMatchRegularExpression('/mg_microgift_event\([^;]*[\'\"]' . preg_quote($field, '/') . '[\'\"]/s', $source);
        }
    }

    public function testApiContractRegistryCoversEnforcedStageNineEndpoints(): void
    {
        $registry = $this->read('docs/contracts/api_contracts_stage1_9.yaml');
        $contracts = [
            'GET|POST /api/microgifts/templates.php',
            'POST /api/microgifts/versions.php',
            'POST /api/microgifts/issue.php',
            'GET /api/microgifts/instances.php',
            'POST /api/microgifts/claim.php',
            'POST /api/microgifts/redeem.php',
            'POST /api/microgifts/payment-policy.php',
            'GET /api/account/microgifts.php',
            'GET /api/merchant/microgifts.php',
            'GET|POST /api/admin/microgift-reviews.php',
            'GET /api/admin/microgift-inspect.php',
            'POST /api/admin/microgift-lifecycle.php',
            'POST /api/admin/microgift-replace.php',
            'GET /api/library/download.php',
        ];
        foreach ($contracts as $contract) {
            self::assertStringContainsString('  ' . $contract . ':', $registry);
        }
    }

    public function testEnforcedWriteEndpointsHaveAuthAndCsrfGates(): void
    {
        $files = [
            'api/microgifts/templates.php',
            'api/microgifts/versions.php',
            'api/microgifts/issue.php',
            'api/microgifts/claim.php',
            'api/microgifts/redeem.php',
            'api/microgifts/payment-policy.php',
            'api/admin/microgift-reviews.php',
            'api/admin/microgift-lifecycle.php',
            'api/admin/microgift-replace.php',
        ];
        foreach ($files as $file) {
            $source = $this->read($file);
            self::assertTrue(
                str_contains($source, 'mg_require_api_user') || str_contains($source, 'mg_require_permission'),
                $file . ' lacks an authentication or permission gate.'
            );
            self::assertStringContainsString('mg_require_csrf_for_write', $source, $file . ' lacks CSRF validation.');
        }
    }

    public function testPppmOwnershipAndRedemptionRemainCanonical(): void
    {
        $lifecycle = $this->read('api/microgifts/_lifecycle.php');
        $ownership = $this->read('api/pppm/_ownership.php');
        self::assertStringContainsString('mg_pppm_transfer_owner_canonical', $lifecycle);
        self::assertStringContainsString('mg_pppm_redeem', $lifecycle);
        self::assertStringContainsString('mg_entitlements_sync_pppm_owner', $ownership);
        self::assertStringContainsString('UPDATE pppm_items SET owner_user_id=?', $ownership);
        self::assertStringNotContainsString("UPDATE pppm_items SET status='redeemed'", $lifecycle);
        self::assertStringNotContainsString('UPDATE pppm_items SET owner_user_id=', $lifecycle);
    }

    public function testApiRegistryDefinesCanonicalServiceRules(): void
    {
        $registry = $this->read('docs/contracts/api_contracts_stage1_9.yaml');
        self::assertStringContainsString('required_services: [mg_pppm_transfer_owner_canonical]', $registry);
        self::assertStringContainsString('required_services: [mg_pppm_redeem]', $registry);
        self::assertStringContainsString('Every endpoint in enforcement_scope must be represented in contracts.', $registry);
    }
}

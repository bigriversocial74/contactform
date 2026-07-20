<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/api/internal/_mcp_bridge.php';

final class McpPhase1CanonicalBridgeV1ContractTest extends TestCase
{
    public function testHmacContractMatchesCanonicalPayload(): void
    {
        $secret = 'bridge-test-secret-with-more-than-thirty-two-characters';
        $timestamp = '1784577600';
        $nonce = 'abcdefghijklmnop';
        $body = json_encode(['operation' => 'connection.resolve'], JSON_THROW_ON_ERROR);
        $payload = $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);

        self::assertSame($payload, mg_mcp_bridge_canonical_signature_payload($timestamp, $nonce, $body));
        self::assertSame(hash_hmac('sha256', $payload, $secret), mg_mcp_bridge_expected_signature($secret, $timestamp, $nonce, $body));
    }

    public function testProductCursorIsSignedToItsFilters(): void
    {
        $filters = ['query' => 'coffee', 'type' => 'merchant', 'location' => 'phoenix', 'category' => 'gift'];
        $signature = mg_product_discovery_cursor_signature($filters);
        $cursor = mg_product_discovery_cursor_encode([
            'signature' => $signature,
            'primary' => 1,
            'published_at' => '2026-07-20 12:00:00',
            'id' => '11111111-1111-4111-8111-111111111111',
        ]);

        $decoded = mg_product_discovery_cursor_decode($cursor, $signature);
        self::assertIsArray($decoded);
        self::assertSame(1, (int)$decoded['primary']);
        self::assertSame('11111111-1111-4111-8111-111111111111', $decoded['id']);

        $this->expectException(InvalidArgumentException::class);
        mg_product_discovery_cursor_decode($cursor, str_repeat('0', 64));
    }

    public function testReleaseKeepsReadOnlyBoundaries(): void
    {
        $release = require dirname(__DIR__, 2) . '/config/mcp_phase1_canonical_bridge_release.php';
        self::assertSame([], $release['required_migrations']);
        self::assertContains('no_node_database_credentials', $release['boundaries']);
        self::assertContains('no_write_tools', $release['boundaries']);
        self::assertSame('catalog:read', $release['operations']['catalog.search']);
        self::assertSame('catalog:read', $release['operations']['catalog.get_item']);
    }

    public function testPrivacyProjectionOmitsExactAddressAndPrivateMetadata(): void
    {
        $helper = file_get_contents(dirname(__DIR__, 2) . '/api/internal/_mcp_bridge.php');
        self::assertIsString($helper);
        self::assertStringNotContainsString("'address_line1' =>", $helper);
        self::assertStringNotContainsString("'postal_code' =>", $helper);
        self::assertStringNotContainsString("'metadata' =>", $helper);
        self::assertStringContainsString("'city' =>", $helper);
        self::assertStringContainsString("'region' =>", $helper);
    }
}

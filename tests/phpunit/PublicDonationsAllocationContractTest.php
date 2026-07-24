<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/public-donations-allocation.php';

final class PublicDonationsAllocationContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testRecipientNormalizationSortsAndDetectsMode(): void
    {
        $rows = mg_public_donations_allocation_recipients([
            ['assignment_id' => '223e4567-e89b-42d3-a456-426614174002', 'quantity' => 2],
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174001', 'quantity' => 2],
        ]);
        self::assertSame('123e4567-e89b-42d3-a456-426614174001', $rows[0]['assignment_id']);
        self::assertSame('same_quantity', mg_public_donations_allocation_mode($rows));
        $rows[1]['quantity'] = 3;
        self::assertSame('custom_quantity', mg_public_donations_allocation_mode($rows));
        self::assertSame('single', mg_public_donations_allocation_mode([$rows[0]]));
    }

    public function testDuplicateRecipientIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174001', 'quantity' => 1],
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174001', 'quantity' => 2],
        ]);
    }

    public function testRecipientAndUnitLimitsAreEnforced(): void
    {
        $tooMany = [];
        for ($index = 0; $index < 51; $index++) {
            $tooMany[] = [
                'assignment_id' => sprintf('123e4567-e89b-42d3-a456-%012d', $index),
                'quantity' => 1,
            ];
        }
        try {
            mg_public_donations_allocation_recipients($tooMany);
            self::fail('Expected recipient limit failure.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('50 Community accounts', $error->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1,000 reward units');
        mg_public_donations_allocation_recipients([
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174001', 'quantity' => 600],
            ['assignment_id' => '223e4567-e89b-42d3-a456-426614174002', 'quantity' => 401],
        ]);
    }

    public function testRequestHashIsOrderIndependentAfterNormalization(): void
    {
        $a = mg_public_donations_allocation_recipients([
            ['assignment_id' => '223e4567-e89b-42d3-a456-426614174002', 'quantity' => 2],
            ['assignment_id' => '123e4567-e89b-42d3-a456-426614174001', 'quantity' => 1],
        ]);
        $b = mg_public_donations_allocation_recipients(array_reverse($a));
        self::assertSame(
            mg_public_donations_allocation_request_hash('campaign-a', '123e4567-e89b-42d3-a456-426614174010', $a, 'Hello', 'Note'),
            mg_public_donations_allocation_request_hash('campaign-a', '123e4567-e89b-42d3-a456-426614174010', $b, 'Hello', 'Note')
        );
    }

    public function testInventoryRejectsOversubscription(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient');
        mg_public_donations_allocation_inventory(
            ['quantity_limit' => 10, 'issued_count' => 8],
            ['quantity_limit' => 20, 'issued_count' => 5],
            3
        );
    }

    public function testUnlimitedInventoryRemainsUnlimited(): void
    {
        $inventory = mg_public_donations_allocation_inventory(
            ['quantity_limit' => null, 'issued_count' => 500],
            ['quantity_limit' => null, 'issued_count' => 900],
            100
        );
        self::assertNull($inventory['available_before']);
        self::assertNull($inventory['available_after']);
    }

    public function testIdempotencyKeyValidation(): void
    {
        self::assertSame('public-donation:request-1234', mg_public_donations_allocation_idempotency_key('public-donation:request-1234'));
        $this->expectException(RuntimeException::class);
        mg_public_donations_allocation_idempotency_key('bad key with spaces');
    }

    public function testSourceUsesCanonicalLifecycleAndNoPurchaseFlow(): void
    {
        $engine = (string)file_get_contents($this->root . '/includes/public-donations-allocation.php');
        $endpoint = (string)file_get_contents($this->root . '/api/merchant/public-donations-allocation.php');
        self::assertStringContainsString('mg_zero_reward_issue_from_wallet', $engine);
        self::assertStringContainsString("'public_donation'", $engine);
        self::assertStringContainsString('beginTransaction()', $engine);
        self::assertStringContainsString('FOR UPDATE', $engine);
        self::assertStringContainsString('rollBack()', $engine);
        self::assertStringContainsString("'allow_self' => true", $engine);
        self::assertStringContainsString("'public_purchase' => false", $endpoint);
        self::assertDoesNotMatchRegularExpression('/\b(?:payment_intent|charge_customer|checkout_session)\b/i', $engine . "\n" . $endpoint);
    }

    public function testClientUsesSafeDomConstruction(): void
    {
        $client = (string)file_get_contents($this->root . '/assets/js/public-donations-allocation.js');
        self::assertStringNotContainsString('.innerHTML', $client);
        self::assertStringContainsString('textContent', $client);
        self::assertStringContainsString('replaceChildren', $client);
    }
}

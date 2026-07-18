<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UserContactListsFoundationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function testSchemaUsesNormalizedListsContactsAndMemberships(): void
    {
        $sql = file_get_contents($this->root . '/database/20260714_user_contact_lists_phase1.sql');
        self::assertIsString($sql);
        foreach (['user_contact_lists','user_contacts','user_contact_list_members','user_contact_dates','user_contact_profile_permissions','user_contact_profile_imports'] as $table) {
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ' . $table, $sql);
        }
        self::assertStringContainsString('UNIQUE KEY uq_user_contact_list_member_linked', $sql);
        self::assertStringContainsString('UNIQUE KEY uq_user_contact_list_member_private', $sql);
        self::assertStringNotContainsString('contact_ids_csv', $sql);
    }

    public function testEligibilityRequiresMutualActiveFollowsAndRejectsBlocks(): void
    {
        $service = file_get_contents($this->root . '/includes/user-contact-lists.php');
        self::assertIsString($service);
        self::assertStringContainsString('function mg_user_contact_list_eligible', $service);
        self::assertStringContainsString('social_follows', $service);
        self::assertStringContainsString('social_blocks', $service);
        self::assertStringContainsString('$ownerFollows', $service);
        self::assertStringContainsString('$contactFollows', $service);
        self::assertStringContainsString('allow_list_membership', $service);
    }

    public function testDiscoveryIsLimitedToFollowRelationshipsAndVisibleProfiles(): void
    {
        $search = file_get_contents($this->root . '/includes/user-contact-search.php');
        $endpoint = file_get_contents($this->root . '/api/user-lists/search-contacts.php');
        self::assertIsString($search);
        self::assertIsString($endpoint);
        self::assertStringContainsString('INNER JOIN social_follows sf_rel', $search);
        self::assertStringContainsString("sf_rel.status='active'", $search);
        self::assertStringContainsString("pp.status='active'", $search);
        self::assertStringContainsString("pp.visibility IN ('public','unlisted')", $search);
        self::assertStringContainsString('mg_user_contact_relationship_search', $endpoint);
    }

    public function testPhoneStorageIsEncryptedAndStandardPayloadsAreMasked(): void
    {
        $service = file_get_contents($this->root . '/includes/user-contact-lists.php');
        self::assertIsString($service);
        self::assertStringContainsString("'aes-256-gcm'", $service);
        self::assertStringContainsString("mg_env('MG_CONTACT_DATA_KEY'", $service);
        self::assertStringContainsString("'phone_masked'", $service);
        self::assertStringNotContainsString("'phone' =>", $service);

        foreach (glob($this->root . '/api/user-{lists,contacts}/*.php', GLOB_BRACE) ?: [] as $file) {
            $content = file_get_contents($file);
            self::assertIsString($content);
            self::assertStringNotContainsString('phone_ciphertext', $content, basename($file));
        }
    }

    public function testCreateCenterListDoesNotGrantMerchantTools(): void
    {
        $header = file_get_contents($this->root . '/includes/header-templates/logged-in.php');
        $extension = file_get_contents($this->root . '/includes/header-components/create-list-extension.php');
        self::assertIsString($header);
        self::assertIsString($extension);
        self::assertStringContainsString('$can_create_list', $header);
        self::assertStringContainsString('$can_merchant_nav && ($can_create_microgift || $can_create_campaigns || $can_create_rewards)', $header);
        self::assertStringContainsString('data-create-menu-option="contact_list"', $extension);
        self::assertStringContainsString('data-create-inline-form="list"', $extension);
    }

    public function testExistingAgentShellAndStickyComposerRemainInPlace(): void
    {
        $agent = file_get_contents($this->root . '/agent.php');
        $workspace = file_get_contents($this->root . '/includes/agent-workspace.php');
        $css = file_get_contents($this->root . '/assets/css/agent-workspace-layout.css');
        self::assertIsString($agent);
        self::assertIsString($workspace);
        self::assertIsString($css);
        self::assertStringContainsString("require __DIR__ . '/includes/agent-workspace.php';", $agent);
        self::assertStringContainsString('data-agent-composer', $workspace);
        self::assertStringContainsString('.mg-agent-workspace .mg-app-composer', $css);
    }

    public function testAgentTabIsAvailableToAuthenticatedCustomers(): void

    {
        $header = file_get_contents($this->root . '/includes/header-components/app-header.php');
        self::assertIsString($header);
        self::assertStringContainsString('$is_authenticated_user = mg_current_user() !== null;', $header);
        self::assertStringContainsString("['agent','Agent','/agent.php',\$can_agent_workspace]", $header);



    }
}

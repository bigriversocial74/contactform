<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SchemaV2ActionCenterCrmAddendumContractTest extends TestCase
{
    private static function schemaSql(): string
    {
        $path = dirname(__DIR__, 2) . '/database/schema_v2_action_center_crm_addendum.sql';
        self::assertFileExists($path);
        $sql = file_get_contents($path);
        self::assertIsString($sql);
        return $sql;
    }

    /** @return array<string,array<int,string>> */
    private static function parseCreateTables(string $sql): array
    {
        $tables = [];
        preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+([a-zA-Z0-9_]+)\s*\((.*?)\)\s+ENGINE/is', $sql, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $columns = [];
            foreach (preg_split('/\R/', $match[2]) ?: [] as $line) {
                $line = trim($line);
                if (preg_match('/^([a-zA-Z0-9_]+)\s+/', $line, $columnMatch)) {
                    $name = strtolower($columnMatch[1]);
                    if (!in_array($name, ['primary', 'unique', 'key', 'constraint'], true)) {
                        $columns[] = $name;
                    }
                }
            }
            $tables[strtolower($match[1])] = $columns;
        }
        return $tables;
    }

    public function testAddendumDefinesAllCurrentPostStageNineTables(): void
    {
        $tables = self::parseCreateTables(self::schemaSql());
        foreach ([
            'microgift_inbox_items',
            'crm_leads',
            'crm_lead_events',
            'crm_lead_assignments',
            'crm_lead_notes',
            'sales_roster',
            'sales_presence',
            'employee_chat_messages',
            'website_analytics_events',
        ] as $table) {
            self::assertArrayHasKey($table, $tables, "Missing {$table} in schema v2 addendum.");
        }
    }

    public function testActionCenterProjectionColumnsMatchCurrentCodeUsage(): void
    {
        $tables = self::parseCreateTables(self::schemaSql());
        $columns = $tables['microgift_inbox_items'] ?? [];
        foreach ([
            'id','public_id','instance_id','user_id','folder','state','sender_user_id','recipient_user_id',
            'redemption_id','merchant_user_id','location_id','can_tip','read_at','archived_at',
            'first_received_at','sent_at','claimed_at','redeemed_at','metadata_json','created_at','updated_at',
        ] as $column) {
            self::assertContains($column, $columns, "microgift_inbox_items.{$column} is required by Action Center code.");
        }

        $actionCenter = file_get_contents(dirname(__DIR__, 2) . '/api/account/_action_center.php');
        $projection = file_get_contents(dirname(__DIR__, 2) . '/api/microgifts/_action_center_projection.php');
        self::assertIsString($actionCenter);
        self::assertIsString($projection);
        self::assertStringContainsString('microgift_inbox_items', $actionCenter);
        self::assertStringContainsString('microgift_inbox_items', $projection);
    }

    public function testCrmColumnsMatchCurrentCodeUsage(): void
    {
        $tables = self::parseCreateTables(self::schemaSql());
        $required = [
            'crm_leads' => ['id','public_id','lead_type','source_page','source_url','source_utm_json','name','email','phone','business_name','website_url','zip_code','category','message','status','priority','assigned_user_id','assigned_at','region_country','region_state','region_city','region_postal','ip_hash','user_agent_hash','metadata_json','created_at','updated_at'],
            'crm_lead_events' => ['id','public_id','lead_id','event_type','from_status','to_status','actor_user_id','note','metadata_json','created_at'],
            'crm_lead_assignments' => ['id','public_id','lead_id','assigned_to_user_id','assigned_by_user_id','assignment_method','reason','created_at'],
            'crm_lead_notes' => ['id','public_id','lead_id','user_id','note','visibility','created_at','updated_at'],
            'sales_roster' => ['id','public_id','user_id','status','territory','region_code','lead_weight','max_open_leads','open_lead_count','last_assigned_at','created_at','updated_at'],
            'sales_presence' => ['user_id','status','last_seen_at','created_at','updated_at'],
            'employee_chat_messages' => ['id','public_id','sender_user_id','recipient_user_id','message','sent_while_offline','read_at','created_at'],
            'website_analytics_events' => ['id','public_id','event_type','source_page','path','referrer','utm_source','utm_medium','utm_campaign','utm_term','utm_content','region_country','region_state','region_city','region_postal','timezone_label','ip_hash','user_agent_hash','session_key_hash','metadata_json','created_at'],
        ];

        foreach ($required as $table => $columns) {
            self::assertArrayHasKey($table, $tables, "Missing {$table}.");
            foreach ($columns as $column) {
                self::assertContains($column, $tables[$table], "{$table}.{$column} is required by CRM code.");
            }
        }

        $crm = file_get_contents(dirname(__DIR__, 2) . '/includes/crm.php');
        $chatThread = file_get_contents(dirname(__DIR__, 2) . '/api/sales/chat/thread.php');
        $presence = file_get_contents(dirname(__DIR__, 2) . '/api/sales/presence.php');
        self::assertIsString($crm);
        self::assertIsString($chatThread);
        self::assertIsString($presence);
        foreach (array_keys($required) as $table) {
            self::assertStringContainsString($table, self::schemaSql());
        }
        foreach (['crm_leads','crm_lead_events','crm_lead_assignments','crm_lead_notes','sales_roster','website_analytics_events'] as $table) {
            self::assertStringContainsString($table, $crm);
        }
        self::assertStringContainsString('employee_chat_messages', $chatThread);
        self::assertStringContainsString('sales_presence', $presence);
    }
}

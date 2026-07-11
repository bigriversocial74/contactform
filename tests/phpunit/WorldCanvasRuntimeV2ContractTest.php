<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

final class WorldCanvasRuntimeV2ContractTest extends TestCase
{
    public function testWorldCanvasUsesOneGeographicRuntimeAndThreeGameplayLayer(): void
    {
        $page=file_get_contents(dirname(__DIR__,2).'/world-canvas.php');
        $runtime=file_get_contents(dirname(__DIR__,2).'/assets/js/world-canvas-runtime-v2.js');
        self::assertIsString($page);self::assertIsString($runtime);
        foreach(['maplibre-gl@5.7.1','three@0.160.0','/assets/js/world-canvas-runtime-v2.js','data-world-persona-select','data-world-maplibre'] as $needle)self::assertStringContainsString($needle,$page);
        self::assertStringNotContainsString('/assets/js/world-canvas-square-map.js',$page);
        self::assertStringNotContainsString('/assets/js/world-canvas-geo-zoom',$page);
        foreach(['new window.maplibregl.Map','new window.maplibregl.Marker','draggable:Boolean(d.owned)','new window.THREE.WebGLRenderer',"MG.post('/api/world-canvas/persona.php'", "MG.post('/api/world-canvas/location-presence.php'"] as $needle)self::assertStringContainsString($needle,$runtime);
    }

    public function testActivityRuntimeUsesCanonicalMerchantAndUserGeo(): void
    {
        $activity=file_get_contents(dirname(__DIR__,2).'/api/world-canvas/activity.php');
        $normalizer=file_get_contents(dirname(__DIR__,2).'/api/world-canvas/_runtime_v2.php');
        self::assertIsString($activity);self::assertIsString($normalizer);
        self::assertStringContainsString("require_once __DIR__ . '/_runtime_v2.php'",$activity);
        self::assertStringContainsString('mg_world_canvas_runtime_v2($pdo, $user, $payload)',$activity);
        foreach(["'merchant_location_source' => 'merchant_locations'","'user_location_source' => 'user_world_positions'","'random_geo_fallback' => false",'entered_registered_merchant_location','entity_key'] as $needle)self::assertStringContainsString($needle,$normalizer);
    }

    public function testMerchantPresencePolicyBlocksOrAllowsUnattendedEntry(): void
    {
        $presence=file_get_contents(dirname(__DIR__,2).'/api/store/_presence.php');
        $entry=file_get_contents(dirname(__DIR__,2).'/api/store/enter.php');
        $status=file_get_contents(dirname(__DIR__,2).'/api/store/session-status.php');
        $canvas=file_get_contents(dirname(__DIR__,2).'/merchant-canvas.php');
        foreach([$presence,$entry,$status,$canvas] as $source)self::assertIsString($source);
        foreach(["['allow_unattended','temporarily_closed']",'mg_presence_watch','mg_presence_notify_return',"'store_presence'","'merchant_returned'"] as $needle)self::assertStringContainsString($needle,$presence);
        foreach(['mg_presence_handle_entry','merchant_location_id','merchant_presence'] as $needle)self::assertStringContainsString($needle,$entry);
        self::assertStringContainsString("'temporarily_closed'",$status);
        self::assertStringContainsString('/assets/js/merchant-canvas-presence.js',$canvas);
    }

    public function testPresenceMigrationIsRerunnableAndLocationScoped(): void
    {
        $sql=file_get_contents(dirname(__DIR__,2).'/database/stage_33_world_canvas_persona_presence.sql');
        self::assertIsString($sql);
        foreach(['world_presence_mode','world_presence_status','world_presence_cycle','world_canvas_persona_state','mg_store_presence_watchers','merchant_location_id','ON DUPLICATE KEY UPDATE description=VALUES(description)'] as $needle)self::assertStringContainsString($needle,$sql);
    }
}

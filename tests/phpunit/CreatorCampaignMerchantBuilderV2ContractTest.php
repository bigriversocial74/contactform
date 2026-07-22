<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
final class CreatorCampaignMerchantBuilderV2ContractTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=dirname(__DIR__,2);}
    private function source(string $p):string{$v=file_get_contents($this->root.'/'.$p);self::assertIsString($v,$p);return $v;}
    public function testBuilderSchemaAndBoundaries():void{$sql=$this->source('database/20260721_creator_campaign_merchant_builder_v2.sql');self::assertStringContainsString('campaign_focus ENUM',$sql);self::assertStringContainsString('creator_campaign_application_questions',$sql);self::assertStringNotContainsString('commission_basis',$sql);}
    public function testAuthorizationAndLocks():void{$src='';foreach(['core','options','save','validation','duplicate'] as $p)$src.=$this->source('includes/creator-campaigns/builder-'.$p.'.php');self::assertStringContainsString('mg_creator_campaign_actor_context',$src);self::assertStringContainsString('lock_version=lock_version+1',$src);}
    public function testPhaseThreeUnlocksAutomaticAcceptance():void{$view=$this->source('includes/merchant-creator-campaign-builder-view.php');self::assertStringContainsString('name="automatic_acceptance"',$view);self::assertStringNotContainsString('name="automatic_acceptance" disabled',$view);self::assertStringContainsString('Automatically approve creators',$view);}
    public function testRoutesAndMigrationRemainCanonical():void{$nav=$this->source('includes/merchant-navigation.php');$manifest=$this->source('config/migrations.php');self::assertStringContainsString("'creator_campaigns'",$nav);self::assertStringContainsString("'20260721_creator_campaign_merchant_builder_v2.sql'",$manifest);}
}

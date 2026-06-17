<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendContractTest extends TestCase
{
    public function testStableFrontendEntrypointsRetainTheirPublishedContracts(): void
    {
        $root=dirname(__DIR__,2);
        $contracts=require $root.'/config/frontend-contracts.php';

        foreach($contracts['stable_entrypoints'] as $name=>$contract){
            $path=$root.'/'.$contract['path'];
            self::assertFileExists($path,$name.' entrypoint must exist.');
            $source=file_get_contents($path);
            self::assertIsString($source);

            foreach($contract['required_tokens']??[] as $token){
                self::assertStringContainsString($token,$source,$name.' entrypoint lost required contract token: '.$token);
            }
            foreach($contract['forbidden_tokens']??[] as $token){
                self::assertStringNotContainsString($token,$source,$name.' entrypoint must not delegate its published implementation to: '.$token);
            }
        }
    }

    public function testDomContractsRemainCentralizedAndUnique(): void
    {
        $contracts=require dirname(__DIR__,2).'/config/frontend-contracts.php';
        self::assertSame('[data-cart-add],[data-add-to-cart]',$contracts['dom_contracts']['cart_add']);
        self::assertSame('[data-agentic-onboarding]',$contracts['dom_contracts']['agentic_onboarding']);
        self::assertSame('[data-agentic-stage]',$contracts['dom_contracts']['agentic_stage']);
    }

    public function testLegacyCartCoreSplitCannotReturn(): void
    {
        self::assertFileDoesNotExist(dirname(__DIR__,2).'/assets/js/cart-core.js');
    }
}

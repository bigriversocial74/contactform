<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/personal-gifting-agent.php';

function mg_user_agent_api_run(callable $callback): never
{
    try {
        $result=$callback();
        mg_ok(is_array($result)?$result:[]);
    } catch (InvalidArgumentException $e) {
        mg_fail($e->getMessage(),422);
    } catch (RuntimeException $e) {
        $message=$e->getMessage();
        $lower=mb_strtolower($message);
        $status=str_contains($lower,'not found')?404:(str_contains($lower,'migration')?503:409);
        mg_fail($message,$status);
    } catch (Throwable $e) {
        if (function_exists('mg_security_log')) {
            mg_security_log('error','user_agent.api_failed','Personal gifting agent API request failed.',['exception_type'=>get_class($e)],(int)(mg_current_user()['id']??0));
        }
        mg_fail('Unable to complete the Personal Gifting Agent request.',500);
    }
}

<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/user-contact-lists.php';

function mg_user_lists_api_run(callable $callback): never
{
    try {
        $result = $callback();
        mg_ok(is_array($result) ? $result : []);
    } catch (InvalidArgumentException $e) {
        mg_fail($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        $status = str_contains(mb_strtolower($message), 'not found') ? 404 : 409;
        mg_fail($message, $status);
    } catch (Throwable $e) {
        if (function_exists('mg_security_log')) {
            mg_security_log('error', 'user_lists.api_failed', 'User lists API request failed.', ['exception' => $e->getMessage()], (int) (mg_current_user()['id'] ?? 0));
        }
        mg_fail('Unable to complete the contact-list request.', 500);
    }
}

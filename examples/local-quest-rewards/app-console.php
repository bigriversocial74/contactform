<?php
declare(strict_types=1);

function lqr_app_console_default(array $config): array
{
    $base = rtrim((string)($config['app_public_url'] ?? ''), '/');
    return [
        'app_id' => 'local_quest_rewards',
        'app_name' => 'Local Quest Rewards',
        'app_status' => 'sandbox',
        'approval_status' => 'approved',
        'callback_url' => $base !== '' ? $base . '/link-callback.php' : 'link-callback.php',
        'webhook_url' => $base !== '' ? $base . '/webhook.php' : 'webhook.php',
        'allowed_programs' => array_values(array_filter([(string)($config['default_program_id'] ?? '')])),
        'allowed_templates' => array_values(array_filter([(string)($config['default_template_id'] ?? '')])),
        'last_api_at' => '',
        'last_webhook_at' => '',
        'review_note' => 'Sandbox app is approved for demo distribution.',
        'updated_at' => gmdate('c'),
    ];
}

function lqr_app_console_settings(array $state, array $config): array
{
    $defaults = lqr_app_console_default($config);
    $saved = is_array($state['partner_app'] ?? null) ? $state['partner_app'] : [];
    return array_replace($defaults, $saved);
}

function lqr_app_console_save(array &$state, array $settings): void
{
    $settings['updated_at'] = gmdate('c');
    $state['partner_app'] = $settings;
}

function lqr_app_console_lines(string $value): array
{
    $items = preg_split('/[\r\n,]+/', $value) ?: [];
    $out = [];
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item !== '') $out[] = $item;
    }
    return array_values(array_unique($out));
}

function lqr_app_status_gate(array $state, array $config, string $programId = '', string $templateId = ''): array
{
    $app = lqr_app_console_settings($state, $config);
    $status = (string)($app['app_status'] ?? 'sandbox');
    $approval = (string)($app['approval_status'] ?? 'approved');
    if ($status === 'disabled') return ['ok'=>false, 'message'=>'Partner app is disabled in App Console.', 'app'=>$app];
    if ($status === 'review' || $approval === 'requested') return ['ok'=>false, 'message'=>'Partner app is waiting for admin approval in App Console.', 'app'=>$app];
    if ($approval === 'rejected') return ['ok'=>false, 'message'=>'Partner app was rejected in App Console.', 'app'=>$app];
    $programs = is_array($app['allowed_programs'] ?? null) ? $app['allowed_programs'] : [];
    $templates = is_array($app['allowed_templates'] ?? null) ? $app['allowed_templates'] : [];
    if ($programId !== '' && $programs && !in_array($programId, $programs, true)) return ['ok'=>false, 'message'=>'Distribution Program is not allowed for this partner app.', 'app'=>$app];
    if ($templateId !== '' && $templates && !in_array($templateId, $templates, true)) return ['ok'=>false, 'message'=>'Reward template is not allowed for this partner app.', 'app'=>$app];
    return ['ok'=>true, 'message'=>'Partner app is allowed.', 'app'=>$app];
}

function lqr_app_console_note_api_call(array &$state): void
{
    $settings = lqr_app_console_settings($state, lqr_config());
    $settings['last_api_at'] = gmdate('c');
    lqr_app_console_save($state, $settings);
}

function lqr_app_console_note_webhook(array &$state): void
{
    $settings = lqr_app_console_settings($state, lqr_config());
    $settings['last_webhook_at'] = gmdate('c');
    lqr_app_console_save($state, $settings);
}

<?php
declare(strict_types=1);

function lqr_builder_default_settings(array $config, array $quests): array
{
    $defaultProgram = trim((string)($config['default_program_id'] ?? ''));
    $defaultTemplate = trim((string)($config['default_template_id'] ?? ''));
    $programs = [
        'local_quest_demo' => ['key'=>'local_quest_demo','name'=>'Local Quest Demo Program','status'=>'sandbox','budget'=>'500 demo cap','program_id'=>$defaultProgram,'template_id'=>$defaultTemplate,'issue_limit'=>'1 reward per quest action','access_mode'=>'sandbox'],
        'venue_night' => ['key'=>'venue_night','name'=>'Venue Night Rewards','status'=>'draft','budget'=>'merchant controlled','program_id'=>$defaultProgram,'template_id'=>$defaultTemplate,'issue_limit'=>'1 per QR scan','access_mode'=>'review'],
        'food_crawl' => ['key'=>'food_crawl','name'=>'Downtown Food Crawl','status'=>'draft','budget'=>'merchant controlled','program_id'=>$defaultProgram,'template_id'=>$defaultTemplate,'issue_limit'=>'1 per milestone','access_mode'=>'review'],
    ];
    $mappings = [];
    foreach ($quests as $questId => $quest) {
        if (!is_array($quest)) continue;
        $key = 'local_quest_demo';
        if (str_contains((string)$questId, 'music')) $key = 'venue_night';
        if (str_contains((string)$questId, 'food')) $key = 'food_crawl';
        $mappings[(string)$questId] = [
            'quest_id' => (string)$questId,
            'program_key' => $key,
            'status' => 'mapped',
            'template_id' => trim((string)($quest['template_id'] ?: $defaultTemplate)),
            'reward_label' => (string)($quest['reward_label'] ?? 'Microgift reward'),
        ];
    }
    return ['programs'=>$programs,'mappings'=>$mappings,'updated_at'=>gmdate('c')];
}

function lqr_builder_settings(array $state, array $config, array $quests): array
{
    $defaults = lqr_builder_default_settings($config, $quests);
    $saved = is_array($state['merchant_programs'] ?? null) ? $state['merchant_programs'] : [];
    $programs = $defaults['programs'];
    foreach ((is_array($saved['programs'] ?? null) ? $saved['programs'] : []) as $key => $program) {
        if (is_array($program)) $programs[(string)$key] = array_replace($programs[(string)$key] ?? [], $program);
    }
    $mappings = $defaults['mappings'];
    foreach ((is_array($saved['mappings'] ?? null) ? $saved['mappings'] : []) as $questId => $mapping) {
        if (is_array($mapping)) $mappings[(string)$questId] = array_replace($mappings[(string)$questId] ?? [], $mapping);
    }
    return ['programs'=>$programs,'mappings'=>$mappings,'updated_at'=>(string)($saved['updated_at'] ?? $defaults['updated_at'])];
}

function lqr_builder_save_settings(array &$state, array $settings): void
{
    $settings['updated_at'] = gmdate('c');
    $state['merchant_programs'] = $settings;
}

function lqr_builder_issue_gate(array $state, array $config, string $questId, array $quest): array
{
    $settings = lqr_builder_settings($state, $config, lqr_quests());
    $mapping = is_array($settings['mappings'][$questId] ?? null) ? $settings['mappings'][$questId] : [];
    $programKey = (string)($mapping['program_key'] ?? '');
    $program = is_array($settings['programs'][$programKey] ?? null) ? $settings['programs'][$programKey] : [];
    $programId = trim((string)($program['program_id'] ?? lqr_quest_program_id($quest, $config)));
    $templateId = trim((string)($mapping['template_id'] ?? $program['template_id'] ?? lqr_quest_template_id($quest, $config)));
    $mappingStatus = (string)($mapping['status'] ?? 'mapped');
    $programStatus = (string)($program['status'] ?? 'sandbox');
    if ($mappingStatus === 'disabled') return ['ok'=>false,'message'=>'This quest action is disabled in Program Admin.','program_id'=>$programId,'template_id'=>$templateId,'mapping'=>$mapping,'program'=>$program];
    if ($programStatus === 'disabled') return ['ok'=>false,'message'=>'This Distribution Program is disabled in Program Admin.','program_id'=>$programId,'template_id'=>$templateId,'mapping'=>$mapping,'program'=>$program];
    if ($programId === '' || $templateId === '') return ['ok'=>false,'message'=>'Program Admin requires a program ID and reward template before issuing rewards.','program_id'=>$programId,'template_id'=>$templateId,'mapping'=>$mapping,'program'=>$program];
    return ['ok'=>true,'message'=>'Program Admin allows reward issue.','program_id'=>$programId,'template_id'=>$templateId,'mapping'=>$mapping,'program'=>$program,'reward_label'=>(string)($mapping['reward_label'] ?? $quest['reward_label'] ?? 'Microgift reward')];
}

<?php
declare(strict_types=1);

function mg_campaign_followup_delay_seconds(string $preset, ?int $customSeconds = null): int
{
    return match ($preset) {
        '1_hour' => 3600,
        '6_hours' => 21600,
        '1_day' => 86400,
        '15_days' => 1296000,
        'custom' => max(60, (int)($customSeconds ?? 3600)),
        default => 3600,
    };
}

function mg_campaign_followup_install(PDO $pdo): void
{
    if ($pdo->inTransaction()) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_followup_rules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(26) NOT NULL UNIQUE, merchant_user_id BIGINT UNSIGNED NOT NULL, campaign_id BIGINT UNSIGNED NOT NULL, name VARCHAR(160) NOT NULL, trigger_event VARCHAR(80) NOT NULL, delay_preset VARCHAR(40) NOT NULL DEFAULT '1_hour', delay_seconds INT UNSIGNED NOT NULL DEFAULT 3600, channel VARCHAR(40) NOT NULL DEFAULT 'email', message_mode VARCHAR(40) NOT NULL DEFAULT 'template', subject VARCHAR(220) NULL, body TEXT NULL, status VARCHAR(40) NOT NULL DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, KEY idx_followup_rules_campaign (campaign_id), KEY idx_followup_rules_trigger (trigger_event,status,delay_seconds)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_followup_jobs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(26) NOT NULL UNIQUE, merchant_user_id BIGINT UNSIGNED NOT NULL, campaign_id BIGINT UNSIGNED NOT NULL, rule_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NULL, wallet_item_id BIGINT UNSIGNED NULL, trigger_event VARCHAR(80) NOT NULL, due_at DATETIME NOT NULL, status VARCHAR(40) NOT NULL DEFAULT 'queued', attempt_count INT UNSIGNED NOT NULL DEFAULT 0, payload_json JSON NULL, last_error TEXT NULL, sent_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_followup_job_dedupe (rule_id,contact_id,wallet_item_id,trigger_event), KEY idx_followup_jobs_due (status,due_at), KEY idx_followup_jobs_campaign (campaign_id,status), KEY idx_followup_jobs_contact (contact_id), KEY idx_followup_jobs_wallet (wallet_item_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function mg_campaign_followup_schedule(PDO $pdo, array $context): array
{
    mg_campaign_followup_install($pdo);
    $campaignId = (int)($context['campaign_id'] ?? 0);
    $merchantId = (int)($context['merchant_user_id'] ?? 0);
    $trigger = (string)($context['trigger_event'] ?? '');
    if ($campaignId < 1 || $merchantId < 1 || $trigger === '') return ['scheduled'=>0,'jobs'=>[]];
    $stmt = $pdo->prepare("SELECT * FROM campaign_followup_rules WHERE campaign_id=? AND merchant_user_id=? AND trigger_event=? AND status='active'");
    $stmt->execute([$campaignId,$merchantId,$trigger]);
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $jobs = [];
    foreach ($rules as $rule) {
        $jobPublicId = mg_public_id('cfj_');
        $due = gmdate('Y-m-d H:i:s', time() + (int)$rule['delay_seconds']);
        $ins = $pdo->prepare("INSERT IGNORE INTO campaign_followup_jobs (public_id,merchant_user_id,campaign_id,rule_id,contact_id,wallet_item_id,trigger_event,due_at,payload_json) VALUES (?,?,?,?,?,?,?,?,?)");
        $ins->execute([$jobPublicId,$merchantId,$campaignId,(int)$rule['id'],$context['contact_id'] ?? null,$context['wallet_item_id'] ?? null,$trigger,$due,json_encode($context,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        if ($ins->rowCount() > 0) $jobs[] = ['job_id'=>$jobPublicId,'rule_id'=>(string)$rule['public_id'],'due_at'=>$due];
    }
    return ['scheduled'=>count($jobs),'jobs'=>$jobs];
}

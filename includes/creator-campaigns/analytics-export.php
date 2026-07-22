<?php
declare(strict_types=1);

function mg_creator_campaign_analytics_csv_cell(mixed $value): string
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    $value = (string) ($value ?? '');
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/u', $value) === 1) {
        $value = "'" . $value;
    }
    return $value;
}

function mg_creator_campaign_analytics_money_text(array $map): string
{
    $parts = [];
    ksort($map);
    foreach ($map as $currency => $value) {
        if (is_array($value)) {
            foreach ($value as $key => $amount) {
                $parts[] = strtoupper((string) $currency) . ' ' . str_replace('_minor', '', (string) $key) . '=' . number_format(((int) $amount) / 100, 2, '.', '');
            }
        } else {
            $parts[] = strtoupper((string) $currency) . ' ' . number_format(((int) $value) / 100, 2, '.', '');
        }
    }
    return implode('; ', $parts);
}

function mg_creator_campaign_analytics_export_rows(array $analytics, string $report): array
{
    if (!in_array($report, mg_creator_campaign_analytics_report_types(), true)) {
        throw new InvalidArgumentException('Analytics export report is invalid.');
    }

    if ($report === 'campaigns') {
        $headers = ['Campaign ID','Campaign','Status','Merchant','Views','Unique clicks','Engagements','Conversions','Conversion rate %','Leads','Checkouts','Purchases','Claims','Redemptions','Assigned','Completed','Completion rate %','Revision requested','Overdue','Earnings','Payouts','Budgets','Active disputes'];
        $rows = [];
        foreach ($analytics['campaigns'] as $item) {
            $rows[] = [
                $item['public_id'] ?? '', $item['title'] ?? '', $item['status'] ?? '', $item['merchant_name'] ?? '',
                $item['views'] ?? 0, $item['unique_clicks'] ?? 0, $item['engagements'] ?? 0, $item['conversions'] ?? 0,
                number_format(((int) ($item['conversion_rate_bps'] ?? 0)) / 100, 2, '.', ''),
                $item['leads'] ?? 0, $item['checkouts'] ?? 0, $item['purchases'] ?? 0, $item['claims'] ?? 0, $item['redemptions'] ?? 0,
                $item['assigned'] ?? 0, $item['completed'] ?? 0, number_format(((int) ($item['completion_rate_bps'] ?? 0)) / 100, 2, '.', ''),
                $item['revision_requested'] ?? 0, $item['overdue'] ?? 0,
                mg_creator_campaign_analytics_money_text($item['earnings'] ?? []),
                mg_creator_campaign_analytics_money_text($item['payouts'] ?? []),
                mg_creator_campaign_analytics_money_text($item['budgets'] ?? []),
                $item['active_disputes'] ?? 0,
            ];
        }
        return [$headers, $rows];
    }

    if ($report === 'creators') {
        $headers = ['Participant ID','Creator','Campaign ID','Campaign','Merchant','Status','Views','Unique clicks','Engagements','Conversions','Conversion rate %','Assigned','Completed','Completion rate %','Revision requested','Revision rounds','Overdue','Earnings','Payouts','Active disputes'];
        $rows = [];
        foreach ($analytics['creators'] as $item) {
            $rows[] = [
                $item['public_id'] ?? '', $item['creator_name'] ?? 'Creator', $item['campaign_public_id'] ?? '', $item['campaign_title'] ?? '', $item['merchant_name'] ?? '', $item['status'] ?? '',
                $item['views'] ?? 0, $item['unique_clicks'] ?? 0, $item['engagements'] ?? 0, $item['conversions'] ?? 0,
                number_format(((int) ($item['conversion_rate_bps'] ?? 0)) / 100, 2, '.', ''),
                $item['assigned'] ?? 0, $item['completed'] ?? 0, number_format(((int) ($item['completion_rate_bps'] ?? 0)) / 100, 2, '.', ''),
                $item['revision_requested'] ?? 0, $item['revision_rounds'] ?? 0, $item['overdue'] ?? 0,
                mg_creator_campaign_analytics_money_text($item['earnings'] ?? []),
                mg_creator_campaign_analytics_money_text($item['payouts'] ?? []),
                $item['active_disputes'] ?? 0,
            ];
        }
        return [$headers, $rows];
    }

    if ($report === 'channels') {
        $headers = ['Channel','Platform','Sources','Views','Unique clicks','Engagements','Conversions','Conversion rate %'];
        $rows = [];
        foreach ($analytics['channels'] as $item) {
            $rows[] = [$item['channel'] ?? '', $item['platform'] ?? '', $item['source_count'] ?? 0, $item['views'] ?? 0, $item['unique_clicks'] ?? 0, $item['engagements'] ?? 0, $item['conversions'] ?? 0, number_format(((int) ($item['conversion_rate_bps'] ?? 0)) / 100, 2, '.', '')];
        }
        return [$headers, $rows];
    }

    if ($report === 'timeseries') {
        $headers = ['Bucket','Views','Unique clicks','Engagements','Conversions','Earnings','Paid'];
        $rows = [];
        foreach ($analytics['timeseries'] as $item) {
            $rows[] = [$item['bucket'] ?? '', $item['views'] ?? 0, $item['unique_clicks'] ?? 0, $item['engagements'] ?? 0, $item['conversions'] ?? 0, mg_creator_campaign_analytics_money_text($item['earnings'] ?? []), mg_creator_campaign_analytics_money_text($item['paid'] ?? [])];
        }
        return [$headers, $rows];
    }

    $headers = ['Record type','Status','Total'];
    $rows = [];
    foreach (['assignments', 'submissions'] as $type) {
        foreach ($analytics['deliverables'][$type] ?? [] as $item) {
            $rows[] = [$type, $item['status'] ?? '', $item['total'] ?? 0];
        }
    }
    return [$headers, $rows];
}

function mg_creator_campaign_analytics_csv(array $analytics, string $report): array
{
    [$headers, $rows] = mg_creator_campaign_analytics_export_rows($analytics, $report);
    $stream = fopen('php://temp', 'w+');
    if ($stream === false) {
        throw new RuntimeException('Unable to create analytics export.');
    }
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, array_map('mg_creator_campaign_analytics_csv_cell', $headers));
    foreach ($rows as $row) {
        fputcsv($stream, array_map('mg_creator_campaign_analytics_csv_cell', $row));
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);
    if (!is_string($content)) {
        throw new RuntimeException('Unable to read analytics export.');
    }
    $mode = ($analytics['mode'] ?? 'merchant') === 'creator' ? 'creator' : 'merchant';
    $date = gmdate('Ymd');
    return ['filename' => "creator-campaign-{$mode}-{$report}-{$date}.csv", 'content' => $content];
}

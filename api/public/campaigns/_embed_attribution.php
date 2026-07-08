<?php
declare(strict_types=1);

function mg_campaign_embed_attr_text(mixed $value, int $max = 255): string
{
    $value = preg_replace('/\s+/u', ' ', trim((string)$value)) ?? '';
    $value = str_replace(["\0", "\r", "\n"], ' ', $value);
    return $value !== '' ? mb_substr($value, 0, $max) : '';
}

function mg_campaign_embed_attr_url(mixed $value, int $max = 700): string
{
    $value = mg_campaign_embed_attr_text($value, $max);
    if ($value === '') return '';
    if (!preg_match('#^https?://#i', $value)) return '';
    return $value;
}

function mg_campaign_embed_attr_host(string $originOrUrl): string
{
    $originOrUrl = mg_campaign_embed_attr_text($originOrUrl, 700);
    if ($originOrUrl === '') return '';
    $host = parse_url($originOrUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $host = preg_replace('#^https?://#i', '', $originOrUrl) ?? '';
        $host = preg_split('/[\/?#:]/', $host)[0] ?? '';
    }
    $host = strtolower(trim((string)$host));
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    return preg_match('/^[a-z0-9.-]{1,190}$/', $host) ? $host : '';
}

function mg_public_campaign_embed_attribution(array $input): array
{
    $embedSource = mg_campaign_embed_attr_text($input['embed_source'] ?? $input['source_label'] ?? 'website_embed', 120) ?: 'website_embed';
    $embedOrigin = mg_campaign_embed_attr_text($input['embed_origin'] ?? ($_SERVER['HTTP_ORIGIN'] ?? ''), 700);
    $pageUrl = mg_campaign_embed_attr_url($input['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 700);
    $embedMode = mg_campaign_embed_attr_text($input['embed_mode'] ?? $input['display'] ?? $input['mode'] ?? '', 80);
    $originHost = mg_campaign_embed_attr_host((string)($input['origin_host'] ?? ''));
    if ($originHost === '') $originHost = mg_campaign_embed_attr_host($embedOrigin ?: $pageUrl);
    $websiteEmbed = $embedOrigin !== '' || $pageUrl !== '' || $embedMode !== '' || $embedSource === 'website_embed';

    return array_filter([
        'website_embed' => $websiteEmbed,
        'embed_source' => $embedSource,
        'embed_origin' => $embedOrigin,
        'origin_host' => $originHost,
        'page_url' => $pageUrl,
        'embed_mode' => $embedMode,
        'submitted_at' => gmdate('c'),
    ], static fn($value) => $value !== '' && $value !== null);
}

function mg_public_campaign_metadata_with_embed(array $metadata, array $embedAttribution): array
{
    if (!$embedAttribution) return $metadata;
    $metadata['website_embed'] = !empty($embedAttribution['website_embed']);
    $metadata['embed_source'] = (string)($embedAttribution['embed_source'] ?? 'website_embed');
    $metadata['embed_attribution'] = $embedAttribution;
    if (!empty($embedAttribution['origin_host'])) $metadata['origin_host'] = (string)$embedAttribution['origin_host'];
    if (!empty($embedAttribution['page_url'])) $metadata['page_url'] = (string)$embedAttribution['page_url'];
    if (!empty($embedAttribution['embed_mode'])) $metadata['embed_mode'] = (string)$embedAttribution['embed_mode'];
    return $metadata;
}

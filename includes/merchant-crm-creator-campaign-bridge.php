<?php
declare(strict_types=1);

require_once __DIR__ . '/merchant-crm-directory.php';

function mg_merchant_crm_creator_campaign_bridge_installed(PDO $pdo): bool
{
    return mg_merchant_crm_search_table_exists($pdo,'merchant_crm_contact_creator_campaigns')
        && mg_merchant_crm_search_table_exists($pdo,'creator_campaigns');
}

function mg_merchant_crm_creator_campaign_relation_map(PDO $pdo, int $merchantId, array $contactPublicIds): array
{
    $contactPublicIds = array_values(array_unique(array_filter(array_map('strval',$contactPublicIds))));
    if ($merchantId < 1 || $contactPublicIds === [] || !mg_merchant_crm_creator_campaign_bridge_installed($pdo)) return [];
    $contactPublicIds = array_slice($contactPublicIds,0,250);
    $stmt = $pdo->prepare(
        'SELECT mc.public_id crm_contact_public_id,r.relationship_type,r.relationship_status,r.first_event_at,r.last_event_at,
                r.event_count,r.last_event_type,cc.public_id campaign_public_id,cc.title campaign_title,cc.status campaign_status
         FROM merchant_crm_contact_creator_campaigns r
         INNER JOIN merchant_crm_contacts mc ON mc.id=r.crm_contact_id
         INNER JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id
         WHERE r.merchant_user_id=? AND mc.public_id IN ('.implode(',',array_fill(0,count($contactPublicIds),'?')).')
         ORDER BY r.last_event_at DESC,r.id DESC'
    );
    $stmt->execute(array_merge([$merchantId],$contactPublicIds));
    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $id = (string)$row['crm_contact_public_id'];
        $map[$id] ??= [];
        $map[$id][] = [
            'relationship_type'=>(string)$row['relationship_type'],
            'relationship_status'=>(string)$row['relationship_status'],
            'campaign_id'=>(string)$row['campaign_public_id'],
            'campaign_title'=>(string)$row['campaign_title'],
            'campaign_status'=>(string)$row['campaign_status'],
            'first_event_at'=>$row['first_event_at'],
            'last_event_at'=>$row['last_event_at'],
            'event_count'=>(int)$row['event_count'],
            'last_event_type'=>(string)$row['last_event_type'],
            'campaign_url'=>'/merchant-creator-campaign-detail.php?campaign='.rawurlencode((string)$row['campaign_public_id']),
        ];
    }
    return $map;
}

function mg_merchant_crm_creator_campaign_rank(string $type): int
{
    return ['creator_partner'=>5,'customer_lead'=>10,'customer'=>20,'claimant'=>30,'redeemer'=>40][$type] ?? 0;
}

function mg_merchant_crm_creator_campaign_decorate(array $contact, array $relationships): array
{
    if ($relationships === []) {
        $contact['creator_campaign_relationships'] = [];
        $contact['creator_campaign_count'] = 0;
        return $contact;
    }
    usort($relationships,static function(array $a,array $b): int {
        $rank = mg_merchant_crm_creator_campaign_rank((string)$b['relationship_type']) <=> mg_merchant_crm_creator_campaign_rank((string)$a['relationship_type']);
        return $rank !== 0 ? $rank : strcmp((string)$b['last_event_at'],(string)$a['last_event_at']);
    });
    $primary = $relationships[0];
    $campaignIds = array_values(array_unique(array_column($relationships,'campaign_id')));
    $campaignTitles = array_values(array_unique(array_column($relationships,'campaign_title')));
    $types = array_values(array_unique(array_column($relationships,'relationship_type')));
    $contact['creator_campaign_relationships'] = $relationships;
    $contact['creator_campaign_count'] = count($campaignIds);
    $contact['creator_campaign_ids'] = $campaignIds;
    $contact['creator_campaign_titles'] = $campaignTitles;
    $contact['creator_campaign_relationship_types'] = $types;
    $contact['creator_campaign_relationship_type'] = (string)$primary['relationship_type'];
    $contact['creator_campaign_relationship_label'] = ucwords(str_replace('_',' ',(string)$primary['relationship_type']));
    $contact['creator_campaign_title'] = (string)$primary['campaign_title'];
    $contact['creator_campaign_url'] = (string)$primary['campaign_url'];
    if (trim((string)($contact['campaign_title']??'')) === '') $contact['campaign_title'] = (string)$primary['campaign_title'];
    if (trim((string)($contact['campaign_type']??'')) === '') $contact['campaign_type'] = 'creator_campaign';
    $contact['search_index'] = trim((string)($contact['search_index']??'').' '.implode(' ',$campaignTitles).' '.implode(' ',$types).' creator campaign creator partner');
    return $contact;
}

function mg_merchant_crm_creator_campaign_contact_ids(PDO $pdo, int $merchantId, int $limit = 100): array
{
    if ($merchantId < 1 || !mg_merchant_crm_creator_campaign_bridge_installed($pdo)) return [];
    $limit = max(1,min(250,$limit));
    $stmt = $pdo->prepare(
        'SELECT mc.public_id
         FROM merchant_crm_contact_creator_campaigns r
         INNER JOIN merchant_crm_contacts mc ON mc.id=r.crm_contact_id
         WHERE r.merchant_user_id=?
         GROUP BY mc.public_id
         ORDER BY MAX(r.last_event_at) DESC
         LIMIT '.$limit
    );
    $stmt->execute([$merchantId]);
    return array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function mg_merchant_crm_creator_campaign_recent_contacts(PDO $pdo, int $merchantId, int $limit = 100): array
{
    return mg_merchant_crm_search_contacts_by_ids(
        $pdo,
        $merchantId,
        mg_merchant_crm_creator_campaign_contact_ids($pdo,$merchantId,$limit)
    );
}

function mg_merchant_crm_creator_campaign_matching_contacts(PDO $pdo, int $merchantId, string $query, int $limit = 100): array
{
    $query = mg_merchant_crm_search_query($query);
    if ($query === '' || !mg_merchant_crm_creator_campaign_bridge_installed($pdo)) return [];
    $like = mg_merchant_crm_search_like($query);
    $stmt = $pdo->prepare(
        "SELECT mc.public_id
         FROM merchant_crm_contact_creator_campaigns r
         INNER JOIN merchant_crm_contacts mc ON mc.id=r.crm_contact_id
         INNER JOIN creator_campaigns cc ON cc.id=r.creator_campaign_id
         WHERE r.merchant_user_id=? AND (
           LOWER(COALESCE(cc.title,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(cc.public_id,'')) LIKE ? ESCAPE '\\\\'
           OR LOWER(COALESCE(r.relationship_type,'')) LIKE ? ESCAPE '\\\\' OR LOWER(COALESCE(r.last_event_type,'')) LIKE ? ESCAPE '\\\\'
         ) GROUP BY mc.public_id ORDER BY MAX(r.last_event_at) DESC LIMIT ".max(1,min(100,$limit))
    );
    $stmt->execute([$merchantId,$like,$like,$like,$like]);
    $ids = array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    return mg_merchant_crm_search_contacts_by_ids($pdo,$merchantId,$ids);
}

function mg_merchant_crm_creator_campaign_enrich_directory(PDO $pdo, int $merchantId, array $directory, string $query = ''): array
{
    if (!mg_merchant_crm_creator_campaign_bridge_installed($pdo)) {
        $directory['creator_campaign_schema_ready'] = false;
        return $directory;
    }
    $contacts = is_array($directory['contacts']??null) ? $directory['contacts'] : [];
    $byId = [];
    foreach ($contacts as $contact) {
        $id = (string)($contact['crm_contact_id']??$contact['id']??'');
        if ($id !== '') $byId[$id] = $contact;
    }
    $supplement = trim($query) === ''
        ? mg_merchant_crm_creator_campaign_recent_contacts($pdo,$merchantId,100)
        : mg_merchant_crm_creator_campaign_matching_contacts($pdo,$merchantId,$query,100);
    foreach ($supplement as $contact) {
        $id = (string)($contact['crm_contact_id']??$contact['id']??'');
        if ($id !== '' && !isset($byId[$id])) $byId[$id] = $contact;
    }
    $map = mg_merchant_crm_creator_campaign_relation_map($pdo,$merchantId,array_keys($byId));
    $contacts = [];
    $creatorPartners = 0;$attributedCustomers = 0;
    foreach ($byId as $id=>$contact) {
        $contact = mg_merchant_crm_creator_campaign_decorate($contact,$map[$id]??[]);
        if (!empty($contact['creator_campaign_relationships'])) {
            if (in_array('creator_partner',$contact['creator_campaign_relationship_types'],true)) $creatorPartners++;
            if (array_intersect(['customer_lead','customer','claimant','redeemer'],$contact['creator_campaign_relationship_types'])) $attributedCustomers++;
        }
        $contacts[] = $contact;
    }
    $directory['contacts'] = $contacts;
    $directory['creator_campaign_schema_ready'] = true;
    $directory['creator_campaign_contract_version'] = 12;
    $directory['totals'] = is_array($directory['totals']??null) ? $directory['totals'] : [];
    $directory['totals']['creator_campaign_contacts'] = count(array_filter($contacts,static fn(array $contact): bool => !empty($contact['creator_campaign_relationships'])));
    $directory['totals']['creator_partners'] = $creatorPartners;
    $directory['totals']['creator_attributed_customers'] = $attributedCustomers;
    if ($query !== '') {
        $directory['total'] = count($contacts);
        $directory['has_more'] = false;
        $directory['next_offset'] = null;
    }
    return $directory;
}

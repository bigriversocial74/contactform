<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/commerce/_order_issuance_summary.php';

function mg_task_agent_order_tracking_schema_ready(PDO $pdo): bool
{
    foreach (['commerce_orders','commerce_order_items','receipts','pppm_items','microgift_instances','microgift_inbox_items','multi_agent_shortlist_items'] as $table) {
        if (!mg_personal_agent_table_exists($pdo, $table)) return false;
    }
    return true;
}

function mg_task_agent_order_tracking_items(PDO $pdo, int $userId, int $agentId, int $limit = 20): array
{
    if (!mg_task_agent_order_tracking_schema_ready($pdo)) return [];
    $stmt=$pdo->prepare("SELECT DISTINCT
        o.id order_internal_id,o.public_id order_id,o.currency,o.subtotal_cents,o.discount_cents,o.tax_cents,o.platform_fee_cents,o.total_cents,
        o.payment_status,o.fulfillment_status,o.source_type,o.paid_at,o.cancelled_at,o.created_at,o.updated_at,
        oi.id order_item_internal_id,oi.public_id order_item_id,oi.title_snapshot,oi.quantity,oi.unit_amount_cents,oi.line_total_cents,
        cp.public_id product_id,cpv.public_id product_version_id,
        s.public_id shortlist_id,p.public_id plan_id,p.title plan_title,
        r.public_id receipt_id,r.receipt_number,r.status receipt_status,r.finalized_at
        FROM commerce_orders o
        INNER JOIN commerce_order_items oi ON oi.order_id=o.id
        INNER JOIN catalog_products cp ON cp.id=oi.product_id
        INNER JOIN catalog_product_versions cpv ON cpv.id=oi.product_version_id
        INNER JOIN multi_agent_shortlist_items s ON s.product_version_id=oi.product_version_id
          AND s.owner_user_id=o.buyer_user_id AND s.agent_id=? AND s.status='selected' AND s.plan_id IS NOT NULL
        INNER JOIN user_gifting_plans p ON p.id=s.plan_id AND p.owner_user_id=s.owner_user_id
        LEFT JOIN receipts r ON r.order_id=o.id
        WHERE o.buyer_user_id=?
        ORDER BY o.created_at DESC,o.id DESC,oi.id DESC
        LIMIT ".max(1,min(50,$limit)));
    $stmt->execute([$agentId,$userId]);
    $items=[];
    $issuanceCache=[];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
        $orderInternalId=(int)$row['order_internal_id'];
        if(!isset($issuanceCache[$orderInternalId])){
            $issuanceCache[$orderInternalId]=mg_order_issuance_summary($pdo,[
                'id'=>$orderInternalId,
                'public_id'=>(string)$row['order_id'],
            ],$userId);
        }
        $issuance=$issuanceCache[$orderInternalId];
        $items[]=[
            'order'=>[
                'id'=>(string)$row['order_id'],
                'payment_status'=>(string)$row['payment_status'],
                'fulfillment_status'=>(string)$row['fulfillment_status'],
                'currency'=>(string)$row['currency'],
                'subtotal_cents'=>(int)$row['subtotal_cents'],
                'discount_cents'=>(int)$row['discount_cents'],
                'tax_cents'=>(int)$row['tax_cents'],
                'platform_fee_cents'=>(int)$row['platform_fee_cents'],
                'total_cents'=>(int)$row['total_cents'],
                'paid_at'=>$row['paid_at'] ?: null,
                'cancelled_at'=>$row['cancelled_at'] ?: null,
                'created_at'=>(string)$row['created_at'],
                'updated_at'=>(string)$row['updated_at'],
                'confirmation_url'=>'/checkout-success.php?order='.rawurlencode((string)$row['order_id']),
            ],
            'line'=>[
                'id'=>(string)$row['order_item_id'],
                'title'=>(string)$row['title_snapshot'],
                'quantity'=>(int)$row['quantity'],
                'unit_amount_cents'=>(int)$row['unit_amount_cents'],
                'line_total_cents'=>(int)$row['line_total_cents'],
                'product_id'=>(string)$row['product_id'],
                'product_version_id'=>(string)$row['product_version_id'],
            ],
            'plan'=>[
                'id'=>(string)$row['plan_id'],
                'title'=>(string)$row['plan_title'],
                'shortlist_id'=>(string)$row['shortlist_id'],
                'match_basis'=>'exact_product_version',
            ],
            'receipt'=>$row['receipt_id'] ? [
                'id'=>(string)$row['receipt_id'],
                'number'=>(string)$row['receipt_number'],
                'status'=>(string)$row['receipt_status'],
                'finalized_at'=>$row['finalized_at'] ?: null,
            ] : null,
            'issuance'=>[
                'expected_units'=>(int)$issuance['expected_units'],
                'pppm_items'=>(int)$issuance['pppm_items'],
                'microgifts'=>(int)$issuance['microgifts'],
                'inbox_items'=>(int)$issuance['inbox_items'],
                'issued_units'=>(int)$issuance['issued_units'],
                'missing'=>is_array($issuance['missing']??null)?$issuance['missing']:[],
                'complete'=>!empty($issuance['complete']),
                'state'=>(string)$issuance['state'],
            ],
            'links'=>[
                'confirmation'=>'/checkout-success.php?order='.rawurlencode((string)$row['order_id']),
                'orders'=>'/account/orders.php',
                'commerce_center'=>'/account-commerce.php',
                'inbox'=>'/inbox.php',
            ],
        ];
    }
    return $items;
}

function mg_task_agent_order_tracking_card(array $item): array
{
    $order=is_array($item['order']??null)?$item['order']:[];
    $line=is_array($item['line']??null)?$item['line']:[];
    $plan=is_array($item['plan']??null)?$item['plan']:[];
    $receipt=is_array($item['receipt']??null)?$item['receipt']:null;
    $issuance=is_array($item['issuance']??null)?$item['issuance']:[];
    return [
        'type'=>'purchase_tracking',
        'title'=>(string)($line['title']??'Purchased gift'),
        'body'=>'Exact product-version match for '.((string)($plan['title']??'the selected gift plan')).'. Payment and PPPM issuance are read from canonical commerce records.',
        'order'=>$order,
        'line'=>$line,
        'plan'=>$plan,
        'receipt'=>$receipt,
        'issuance'=>$issuance,
        'links'=>is_array($item['links']??null)?$item['links']:[],
        'action'=>'open_order_confirmation',
        'action_label'=>'Open order confirmation',
        'url'=>(string)($item['links']['confirmation']??''),
        'review_payload'=>[],
        'read_only'=>true,
    ];
}

function mg_task_agent_order_tracking_for_model(array $items): array
{
    return array_map(static function(array $item):array{
        return [
            'plan_title'=>(string)($item['plan']['title']??''),
            'product_title'=>(string)($item['line']['title']??''),
            'quantity'=>(int)($item['line']['quantity']??0),
            'payment_status'=>(string)($item['order']['payment_status']??''),
            'fulfillment_status'=>(string)($item['order']['fulfillment_status']??''),
            'total_cents'=>(int)($item['order']['total_cents']??0),
            'currency'=>(string)($item['order']['currency']??'USD'),
            'receipt_status'=>(string)($item['receipt']['status']??''),
            'issuance_state'=>(string)($item['issuance']['state']??''),
            'expected_units'=>(int)($item['issuance']['expected_units']??0),
            'issued_units'=>(int)($item['issuance']['issued_units']??0),
            'pppm_items'=>(int)($item['issuance']['pppm_items']??0),
            'microgifts'=>(int)($item['issuance']['microgifts']??0),
            'inbox_items'=>(int)($item['issuance']['inbox_items']??0),
            'match_basis'=>'exact_product_version',
            'read_only'=>true,
        ];
    },array_slice($items,0,8));
}

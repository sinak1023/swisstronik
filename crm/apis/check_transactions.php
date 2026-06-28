<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$users_function = new Users($db);
$notes_function = new Notes($db);
$leads_function = new Leads($db);

$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'], true) ?? [];
if ($admin_info['role_id'] > 0) {
    $roles_function = new Roles($db);
    $role = $roles_function->get_by_id($admin_info['role_id']);
    if ($role) {
        $permissions = json_decode($role['permissions'], true) ?? [];
    }
}

$root = 'apis/check_transactions.php';
if (!in_array($root, $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی مشاهده تراکنش ها را ندارید"]);
    exit();
}

$lead_id = $_POST['lead_id'] ?? null;

if (!$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(["ok" => false, "error" => "دسترسی غیرمجاز"]);
    exit();
}

if (!$lead_id) {
    echo json_encode(['success' => false, 'message' => 'اطلاعات ناقص']);
    exit;
}

$lead = $leads_function->get_by_id($lead_id);
if (!$lead) {
    echo json_encode(['success' => false, 'message' => 'لید یافت نشد']);
    exit;
}


// تنظیمات زرین‌پال
$url = 'https://next.zarinpal.com/api/v4/graphql';
$access_token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiMmUwMWJhMTEzYWFkNjNlNmM2ZmFjNWEyMDUwNTRkMDE4MTU0MjUxMzk1MjRkZjdjNTg4ODBiODRkZjA4MmQyYzRhMTU1NDhiNzdmYzZjM2MiLCJpYXQiOjE3NTc4NDE1MjQuNjkwODU3LCJuYmYiOjE3NTc4NDE1MjQuNjkwODY3LCJleHAiOjE5MTU2MDc5MjQuNjMxMDU4LCJzdWIiOiIxNTE5OTQzIiwic2NvcGVzIjpbXX0.YpI6IHx16xa7vonbFInTEK-cfBRAJCPgUQCFpDe9_OpedkAfK3_tkteJ3PdZcT5RZJL457hut6MuCsTy-3Kluk-Uy8JeKW7X-pVD5MFgVOTmSeZ2Ji5fDwreyHqbXaTspKSyclT-pgks-zetH7XttvoB1McenoGYqQ10FhiVZ0CQQrkQbgPNhKlw5XrAX6hX6kGbGEwwmmhjq49Mkr1D-z4axVX96iXqaT--vpC0oqTzYPSUNwB9ac4z5xEMIhZ-quQfV_nyBHKYn7B4jRs3aykRs1szXVLDYayN4TipFaNe2q2sk2gbPIuhFQzFGpAo9SK7rpYZnKHJQ1kn_uEzijinNxHaCfwjAZHLhUH5NxxXUNMeVEHFI5OgtEDPWf2wogGYs7nWySnzgIjowXAy5q3eW-5OaCgUfcRSBzZ0-a83XtfP8rEZuYIUVUOYxCAjqhHOaFFu6I-wGVdgIfYN_XqJB-ZhrVPUDGfa9f03MJTqJqzrJ4JoKCu3jCREZ29lnkXpMClxSQUKzXXimZcRjnDyH_BIhDuDKim3XKkgUepCNcCan-hXQ2N6l5oUO2DzhnQriK4Os5PrK-qDQXOwGtcEaJGvoj2D6FA2xt4yPqQ3UtC8vSJY8u_pR6OzXw2BNlZRHiu1feoF6IDsf5oNW2BygiykZGa_rOTbDrOo5qM';

// GraphQL Query
$query = '
query GetSessions($reconciliation_id: ID, $filter: FilterEnum, $terminal_id: ID, $offset: Int, $limit: Int, $type: SessionTypeEnum, $amount: Int, $note: String, $max_amount: Int, $min_amount: Int, $created_from_date: DateTime, $created_to_date: DateTime, $id: ID, $reference_id: String, $relation_id: ID, $mobile: CellNumber, $email: String, $description: String, $card_pan: String, $rrn: String) {
  Session: Session(
    filter: $filter
    type: $type
    note: $note
    terminal_id: $terminal_id
    offset: $offset
    limit: $limit
    amount: $amount
    max_amount: $max_amount
    min_amount: $min_amount
    mobile: $mobile
    created_from_date: $created_from_date
    created_to_date: $created_to_date
    id: $id
    reference_id: $reference_id
    pagination: true
    relation_id: $relation_id
    card_pan: $card_pan
    email: $email
    description: $description
    rrn: $rrn
    reconciliation_id: $reconciliation_id
  ) {
    id
    type
    status
    created_at
    description
    reconciliation_id
    amount
    fee
    timeline {
      refund_id
      refund_status
      reconciled_id
      reconciled_time
      reconciled_status
      __typename
    }
    __typename
  }
  Pagination {
    total
    last_page
    __typename
  }
}
';

// متغیرهای درخواست
$variables = [
    'filter' => 'PAID',
    'limit' => 50,
    'mobile' => $lead['phone'],
    'offset' => 0,
    'terminal_id' => '373234'
];

// آماده‌سازی داده‌های درخواست
$data = [
    'operationName' => 'GetSessions',
    'variables' => $variables,
    'query' => $query
];

// تنظیم headers
$headers = [
    'Content-Type: application/json',
    'Accept: */*',
    'Authorization: Bearer ' . $access_token,
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36',
    'Origin: https://next.zarinpal.com',
    'Referer: https://next.zarinpal.com/beta/panel/amirhoseintrade.com/session',
    'x-request-type: graphql-without-status'
];

// ایجاد cURL session
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');

// ارسال درخواست
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'message' => 'خطا در ارتباط با زرین‌پال']);
    exit;
}

$result = json_decode($response, true);

if (isset($result['errors'])) {
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات']);
    exit;
}

$newSalesCount = 0;
$transactions = [];

if (isset($result['data']['Session']) && count($result['data']['Session']) > 0) {
    foreach ($result['data']['Session'] as $session) {
        if ($session['status'] === 'PAID') {

            $transactions[] = [
                'id' => $session['id'],
                'date' => $session['created_at'],
                'amount' => number_format($session['amount']/10),
                'description' => $session['description'] ?? '-'
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'new_sales' => $newSalesCount,
    'transactions' => $transactions,
    'total_found' => count($transactions)
]);

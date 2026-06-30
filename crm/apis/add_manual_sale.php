<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

$leads_function = new Leads($db);
$sales = new Sales($db);
$persian = new PersianDate();

$lead_id = (int)($_POST['lead_id'] ?? 0);
$amount_raw = preg_replace('/[^0-9]/', '', Phone::toEnglishDigits($_POST['amount'] ?? ''));
$date_jalali = trim($_POST['sale_date'] ?? '');
$payment_type = ($_POST['payment_type'] ?? 'full') === 'installment' ? 'installment' : 'full';
$period = trim($_POST['period'] ?? '');
$note = trim($_POST['note'] ?? '');

// فقط کارشناسِ صاحب لید (یا مدیر) اجازهٔ ثبت دارد
$user = (new Users($db))->get_by_id($_SESSION['id']);
$is_admin = (int)($user['role_id'] ?? 0) === 1;
if (!$is_admin && !$leads_function->check_lead_permission($lead_id, $_SESSION['id'])) {
    echo json_encode(["ok" => false, "error" => "این لید متعلق به شما نیست"]);
    exit();
}

$lead = $leads_function->get_by_id($lead_id);
if (!$lead) {
    echo json_encode(["ok" => false, "error" => "لید یافت نشد"]);
    exit();
}

if ($amount_raw === '' || (int)$amount_raw <= 0) {
    echo json_encode(["ok" => false, "error" => "مبلغ را به‌صورت عدد (تومان) وارد کنید"]);
    exit();
}

// تبدیل تاریخ شمسی به میلادی (اختیاری؛ پیش‌فرض اکنون)
$transaction_date = date('Y-m-d H:i:s');
if ($date_jalali !== '') {
    $p = preg_split('/[\/\-]/', $persian->tr_num($date_jalali));
    if (count($p) === 3 && (int)$p[0] > 1300) {
        $g = $persian->jalali_to_gregorian((int)$p[0], (int)$p[1], (int)$p[2]);
        $transaction_date = sprintf('%04d-%02d-%02d %s', $g[0], $g[1], $g[2], date('H:i:s'));
    }
}

// آپلود تصویر فیش (اختیاری)
$receipt_image = null;
if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['receipt'];
    if ($f['size'] > 5 * 1024 * 1024) {
        echo json_encode(["ok" => false, "error" => "حجم تصویر نباید بیشتر از ۵ مگابایت باشد"]);
        exit();
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(["ok" => false, "error" => "فقط تصویر (jpg, png, webp) مجاز است"]);
        exit();
    }
    $dir = __DIR__ . '/../uploads/receipts';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fname = 'receipt_' . $lead_id . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) {
        echo json_encode(["ok" => false, "error" => "ذخیرهٔ تصویر ناموفق بود"]);
        exit();
    }
    $receipt_image = 'uploads/receipts/' . $fname;
}

$ref = 'manual_' . $lead_id . '_' . uniqid();

$sale_id = $sales->record([
    'lead_id'          => $lead_id,
    'phone'            => $lead['phone'],
    'user_id'          => $lead['assigned_to'] ?: $_SESSION['id'],
    'project_id'       => $lead['project_id'] ?? null,
    'amount'           => (int)$amount_raw,
    'transaction_ref'  => $ref,
    'transaction_date' => $transaction_date,
    'payment_type'     => $payment_type,
    'source'           => 'manual',
    'receipt_image'    => $receipt_image,
    'period'           => $period ?: null,
    'note'             => $note ?: null,
]);

if (!$sale_id) {
    echo json_encode(["ok" => false, "error" => "ثبت فیش ناموفق بود"]);
    exit();
}

(new ActivityLog($db))->log($_SESSION['id'], 'manual_sale', 'lead', $lead_id, ['amount' => (int)$amount_raw, 'type' => $payment_type]);

echo json_encode([
    "ok" => true,
    "sale" => [
        "id"           => $sale_id,
        "amount"       => number_format((int)$amount_raw),
        "payment_type" => $payment_type,
        "period"       => $period,
        "note"         => $note,
        "receipt_image" => $receipt_image,
        "date_jalali"  => $persian->jdate('Y/m/d', strtotime($transaction_date)),
    ]
]);

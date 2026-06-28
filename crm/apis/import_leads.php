<?php
ini_set('display_errors', '0'); // جلوگیری از خراب شدن خروجی JSON با هشدارهای PHP
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
header('Content-Type: application/json; charset=utf-8');
session_start();

// در صورت بروز خطای کشنده (Fatal)، خروجی JSON معتبر برگردانده شود
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(["ok" => false, "error" => "خطای سرور در پردازش فایل: " . $err['message']]);
    }
});

// سازگاری با PHP 7 (در صورت نبودن این توابع)
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) { return $needle === '' || strpos($haystack, $needle) === 0; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) { return $needle === '' || substr($haystack, -strlen($needle)) === $needle; }
}

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';

// دسترسی
$users_function = new Users($db);
$admin_info = $users_function->get_by_id($_SESSION["id"]);
$permissions = json_decode($admin_info['permissions'] ?? '[]', true);
if ($admin_info['role_id'] > 0) {
    $roles = new Roles($db);
    $role = $roles->get_by_id($admin_info['role_id']);
    if ($role) $permissions = json_decode($role['permissions'] ?? '[]', true);
}
if (!in_array('apis/import_leads.php', $permissions)) {
    echo json_encode(["ok" => false, "error" => "دسترسی ندارید"]);
    exit();
}

// پروژه
$project_id = (int)($_POST['project_id'] ?? 0);
$projects = new Projects($db);
$project = $projects->get_by_id($project_id);
if (!$project || ($project['created_by'] != $_SESSION["id"] && $admin_info['role_id'] != 1)) {
    echo json_encode(["ok" => false, "error" => "دسترسی به پروژه ندارید"]);
    exit();
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'حجم فایل از حد مجاز سرور بیشتر است',
        UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است',
        UPLOAD_ERR_PARTIAL => 'فایل به‌صورت ناقص آپلود شد',
        UPLOAD_ERR_NO_FILE => 'فایلی انتخاب نشد',
        UPLOAD_ERR_NO_TMP_DIR => 'پوشهٔ موقت سرور موجود نیست',
        UPLOAD_ERR_CANT_WRITE => 'خطا در نوشتن فایل روی سرور',
    ];
    $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(["ok" => false, "error" => $upload_errors[$code] ?? "فایل آپلود نشد"]);
    exit();
}

// آزادسازی قفل نشست قبل از پردازش (احتمالاً کند) فایل
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$file = $_FILES['file'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$tmp_path = $file['tmp_name'];
$content = file_get_contents($tmp_path);

// حذف BOM ابتدای فایل (یونی‌کد)
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

$preview = !empty($_POST['preview']);
$name_columns = $_POST['name_columns'] ?? [];
if (is_string($name_columns)) $name_columns = json_decode($name_columns, true) ?? [];
$name_columns = array_map('intval', array_filter((array)$name_columns, 'is_numeric'));

$phone_column = $_POST['phone_column'] ?? null;
if (is_string($phone_column) && $phone_column !== '') $phone_column = (int)$phone_column;
else if ($phone_column === '') $phone_column = null;

$ignore_name = !empty($_POST['ignore_name']);
$delimiter = $_POST['delimiter'] ?? null;

$leads_func = new Leads($db);
$leads = [];
$line_count = $imported = $skipped = 0;
$errors = [];

function clean_phone($phone)
{
    return preg_replace('/[^0-9]/', '', trim((string)$phone));
}

try {
    $headers = [];
    $dataRows = [];

    if ($ext === 'xlsx') {
        // خواندن بومی xlsx (بدون نیاز به PhpSpreadsheet) — xlsx یک فایل zip از XML است
        $all = parse_xlsx($tmp_path);
        if (!empty($all)) {
            $headers = array_map(function ($v) { return trim((string)$v); }, $all[0]);
            $dataRows = array_slice($all, 1);
        }
    } elseif ($ext === 'xls') {
        // فرمت قدیمی باینری xls پشتیبانی نمی‌شود (برای جلوگیری از وابستگی به PhpSpreadsheet)
        echo json_encode(["ok" => false, "error" => "فرمت قدیمی xls پشتیبانی نمی‌شود. لطفاً در اکسل با گزینهٔ «Save As» فایل را به‌صورت xlsx یا CSV ذخیره و آپلود کنید."]);
        exit();
    } elseif ($ext === 'csv') {
        // CSV به‌صورت بومی (بدون نیاز به PhpSpreadsheet)
        $csv_delim = $delimiter ?: detect_delimiter(substr($content, 0, 2000));
        $all = [];
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') continue;
            $all[] = array_map('trim', str_getcsv($line, $csv_delim));
        }
        if (!empty($all)) {
            $headers = array_map(function ($v) { return trim((string)$v); }, $all[0]);
            $dataRows = array_slice($all, 1);
        }
    } elseif ($ext === 'txt') {
        // TXT ساده (شماره‌ها پشت‌سرهم یا نام/شماره با جداکننده)
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $content)), function ($l) { return $l !== ''; }));
        if (empty($lines)) {
            echo json_encode(["ok" => false, "error" => "فایل خالی است"]);
            exit();
        }

        if ($preview) {
            $detected = $delimiter ?: detect_delimiter(implode("\n", array_slice($lines, 0, 10)));
            $sample = [];
            foreach (array_slice($lines, 0, 5) as $l) {
                $sample[] = array_map('trim', str_getcsv($l, $detected));
            }
            echo json_encode([
                "ok" => true,
                "preview" => true,
                "headers" => [],
                "sample" => $sample,
                "delimiter" => $detected,
                "is_txt" => true
            ]);
            exit();
        }

        $delimiter = $_POST['delimiter'] ?? ',';
        $has_name = !empty($_POST['has_name']);
        $name_first = !empty($_POST['name_first']);

        foreach ($lines as $line) {
            $row = array_map('trim', str_getcsv($line, $delimiter));
            $line_count++;
            if (count($row) < 1) continue;

            $phone = '';
            $name = 'بینام';
            if ($has_name && count($row) >= 2) {
                if ($name_first) { $name = $row[0]; $phone = $row[1] ?? ''; }
                else { $name = $row[1] ?? 'بینام'; $phone = $row[0]; }
            } else {
                // اگر چند مقدار در یک خط بود، اولین مقدار عددی را شماره در نظر بگیر
                $phone = $row[0];
            }

            $phone = clean_phone($phone);
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $skipped++;
                continue;
            }
            if ($leads_func->phone_exists_in_project($project_id, $phone)) {
                $skipped++;
                continue;
            }
            $leads[] = ['name' => $name ?: 'بینام', 'phone' => $phone];
        }

        $cross_campaign = cross_campaign_report($db, $project_id, $leads);
        if (!$preview && !empty($leads)) $imported = $leads_func->import($project_id, $leads);
        echo json_encode([
            "ok" => true,
            "imported" => $imported,
            "skipped" => $skipped,
            "total_lines" => $line_count,
            "cross_campaign_count" => count($cross_campaign),
            "cross_campaign" => array_slice($cross_campaign, 0, 100)
        ]);
        exit();
    } elseif (in_array($ext, ['sql', 'db'])) {
        // استخراج INSERT ها از فایل SQL
        $inserts = [];
        $current = '';
        $in_string = false;
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '#')) continue;
            for ($i = 0; $i < strlen($line); $i++) {
                if (!$in_string && ($line[$i] === "'" || $line[$i] === '"')) {
                    $in_string = true;
                } elseif ($in_string && $i > 0 && $line[$i] === $line[$i - 1] && $line[$i - 1] !== '\\') {
                    $in_string = false;
                }
            }
            $current .= $line . " ";
            if (!$in_string && str_ends_with(trim($current), ';')) {
                $q = trim(rtrim(trim($current), ';'));
                if (stripos($q, 'INSERT INTO') === 0) $inserts[] = $q;
                $current = '';
            }
        }
        foreach ($inserts as $q) {
            if (preg_match('/INSERT\s+INTO\s+[`"\']?\w+[`"\']?\s*\((.*?)\)\s*VALUES\s*(.+)/is', $q, $m)) {
                $cols = array_map('trim', explode(',', $m[1]));
                preg_match_all('/\(((?:[^()]+|(?R))*)\)/', $m[2], $vals);
                foreach ($vals[1] as $block) {
                    preg_match_all("/'([^'\\\\]*(?:\\\\.[^'\\\\]*)*)'|\"([^\"\\\\]*(?:\\\\.[^\"\\\\]*)*)\"|([^,]+)/", $block, $v);
                    $values = [];
                    foreach ($v[0] as $val) {
                        $val = trim($val, " \t\n\r\0\x0B,'\"");
                        $values[] = stripslashes($val);
                    }
                    if (count($values) >= count($cols)) {
                        $dataRows[] = array_combine($cols, array_slice($values, 0, count($cols)));
                    }
                }
            }
        }
        $headers = $dataRows ? array_keys($dataRows[0]) : [];
    } elseif ($ext === 'json') {
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("ساختار JSON نامعتبر است");
        $dataRows = is_array($data) && isset($data[0]) ? $data : [$data];
        $headers = $dataRows ? array_keys($dataRows[0]) : [];
    } else {
        echo json_encode(["ok" => false, "error" => "فرمت پشتیبانی نمی‌شود. از CSV، Excel، TXT، JSON یا SQL استفاده کنید."]);
        exit();
    }

    // پیش‌نمایش برای فرمت‌های ساختاریافته
    if ($preview) {
        if (empty($headers)) {
            echo json_encode(["ok" => false, "error" => "ستونی در فایل یافت نشد. ساختار فایل را بررسی کنید."]);
            exit();
        }
        $sample = array_slice($dataRows, 0, 5);
        $sample = array_map(function ($r) { return array_values((array)$r); }, $sample);
        echo json_encode([
            "ok" => true,
            "preview" => true,
            "headers" => $headers,
            "sample" => $sample,
            "is_txt" => false
        ]);
        exit();
    }

    // پردازش نهایی
    foreach ($dataRows as $row) {
        $line_count++;
        $values = is_array($row) ? array_values($row) : [$row];

        // نام
        $name_parts = [];
        foreach ($name_columns as $idx) {
            if (isset($values[$idx])) $name_parts[] = trim((string)$values[$idx]);
        }
        $name = $ignore_name ? 'بینام' : (implode(' ', array_filter($name_parts)) ?: 'بینام');

        // شماره
        $phone = '';
        if ($phone_column !== null && isset($values[$phone_column])) {
            $phone = clean_phone($values[$phone_column]);
        }

        if ($phone === '' || strlen($phone) < 10 || strlen($phone) > 15) {
            $skipped++;
            continue;
        }
        if ($leads_func->phone_exists_in_project($project_id, $phone)) {
            $skipped++;
            continue;
        }

        $leads[] = ['name' => $name, 'phone' => $phone];
    }

    // گزارش مقایسهٔ بین‌کمپینی
    $cross_campaign = cross_campaign_report($db, $project_id, $leads);

    if (!$preview && !empty($leads)) {
        $imported = $leads_func->import($project_id, $leads);
    }

    echo json_encode([
        "ok" => true,
        "imported" => $imported ?? 0,
        "skipped" => $skipped,
        "total_lines" => $line_count,
        "cross_campaign_count" => count($cross_campaign),
        "cross_campaign" => array_slice($cross_campaign, 0, 100)
    ]);
} catch (Throwable $e) {
    echo json_encode(["ok" => false, "error" => "خطا در پردازش فایل: " . $e->getMessage()]);
}

/** تبدیل مرجع ستون اکسل ("B12") به اندیس صفرپایه (1) */
function xlsx_col_to_idx($ref)
{
    preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
    $letters = $m[1] ?? 'A';
    $n = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/**
 * خواندن فایل xlsx به‌صورت بومی با ZipArchive + DOMDocument (بدون PhpSpreadsheet).
 * خروجی: آرایه‌ای از ردیف‌ها که هر ردیف آرایه‌ای از مقادیر ستون‌هاست.
 */
function parse_xlsx($path)
{
    if (!class_exists('ZipArchive')) {
        throw new Exception('افزونهٔ ZipArchive روی سرور فعال نیست؛ فایل را CSV کنید.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('فایل اکسل قابل باز شدن نیست (احتمالاً خراب است).');
    }

    // رشته‌های اشتراکی
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false && $ssXml !== '') {
        $d = new DOMDocument();
        @$d->loadXML($ssXml, LIBXML_NOENT | LIBXML_NONET | LIBXML_PARSEHUGE);
        foreach ($d->getElementsByTagName('si') as $si) {
            $txt = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $txt .= $t->textContent;
            }
            $shared[] = $txt;
        }
    }

    // یافتن اولین شیت
    $sheetName = 'xl/worksheets/sheet1.xml';
    if ($zip->locateName($sheetName) === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nm = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nm)) {
                $sheetName = $nm;
                break;
            }
        }
    }
    $sheetXml = $zip->getFromName($sheetName);
    $zip->close();
    if ($sheetXml === false) {
        throw new Exception('شیت داده در فایل اکسل یافت نشد.');
    }

    $d = new DOMDocument();
    @$d->loadXML($sheetXml, LIBXML_NOENT | LIBXML_NONET | LIBXML_PARSEHUGE);
    $rows = [];
    foreach ($d->getElementsByTagName('row') as $row) {
        $cells = [];
        $max = -1;
        $auto = 0;
        foreach ($row->getElementsByTagName('c') as $c) {
            $ref = $c->getAttribute('r');
            $idx = $ref !== '' ? xlsx_col_to_idx($ref) : $auto;
            $auto = $idx + 1;
            $type = $c->getAttribute('t');
            $val = '';
            if ($type === 'inlineStr') {
                foreach ($c->getElementsByTagName('t') as $tt) {
                    $val .= $tt->textContent;
                }
            } else {
                $vEl = $c->getElementsByTagName('v')->item(0);
                $raw = $vEl ? $vEl->textContent : '';
                $val = ($type === 's') ? ($shared[(int)$raw] ?? '') : $raw;
            }
            $cells[$idx] = trim($val);
            if ($idx > $max) $max = $idx;
        }
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        // ردیف کاملاً خالی را نادیده بگیر
        if (implode('', $line) !== '') {
            $rows[] = $line;
        }
    }
    return $rows;
}

function detect_delimiter($sample)
{
    $delims = [',', ';', "\t", ':', '|'];
    $max = 0;
    $best = ',';
    foreach ($delims as $d) {
        $count = substr_count($sample, $d);
        if ($count > $max) {
            $max = $count;
            $best = $d;
        }
    }
    return $best;
}

/**
 * یافتن شماره‌هایی از این دستهٔ ورودی که در «کمپین/پروژه‌های دیگر» سابقه دارند.
 */
function cross_campaign_report($db, $project_id, $leads)
{
    if (empty($leads)) return [];

    $norm_map = [];
    foreach ($leads as $l) {
        $n = Phone::normalize($l['phone']);
        if ($n !== '') $norm_map[$n] = $l['phone'];
    }
    if (empty($norm_map)) return [];

    $norms = array_slice(array_keys($norm_map), 0, 500);
    $place = implode(',', array_fill(0, count($norms), '?'));

    $params = $norms;
    $params[] = $project_id;
    try {
        $rows = $db->fetchAll(
            "SELECT l.phone_norm, l.phone, l.project_id, p.name AS project_name,
                    l.assigned_to, u.name AS assignee_name, l.created_at
             FROM leads l
             LEFT JOIN projects p ON l.project_id = p.id
             LEFT JOIN users u ON l.assigned_to = u.id
             WHERE l.phone_norm IN ($place) AND l.project_id != ?
             ORDER BY l.created_at DESC",
            $params
        );
    } catch (Throwable $e) {
        return [];
    }

    $report = [];
    $leads_func = new Leads($db);
    foreach ($rows as $r) {
        $key = $r['phone_norm'];
        if (isset($report[$key])) continue;
        $last = $leads_func->get_last_handler($r['phone']);
        $report[$key] = [
            'phone'        => $norm_map[$key] ?? $r['phone'],
            'project_name' => $r['project_name'],
            'last_handler' => $last['user_name'] ?? ($r['assignee_name'] ?? null),
            'last_date'    => $last['assigned_at'] ?? $r['created_at'],
        ];
    }
    return array_values($report);
}

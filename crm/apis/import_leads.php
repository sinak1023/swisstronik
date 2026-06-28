<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

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
    echo json_encode(["ok" => false, "error" => "فایل آپلود نشد"]);
    exit();
}

$file = $_FILES['file'];
$filename = $file['name'];
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$tmp_path = $file['tmp_name'];
$content = file_get_contents($tmp_path);

$preview = !empty($_POST['preview']);
$name_columns = $_POST['name_columns'] ?? [];
if (is_string($name_columns)) $name_columns = json_decode($name_columns, true) ?? [];
$name_columns = array_map('intval', array_filter($name_columns, 'is_numeric'));

$phone_column = $_POST['phone_column'] ?? null;
if (is_string($phone_column)) $phone_column = (int)$phone_column;

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

    // همه فرمت‌ها با PhpSpreadsheet یا پارس دستی
    if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
        // اکسل و CSV با PhpSpreadsheet
        $spreadsheet = IOFactory::load($tmp_path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headers = array_values(array_map('trim', $rows[1] ?? []));
        $dataRows = array_slice($rows, 2);
    } elseif (in_array($ext, ['txt', 'db', 'sql'])) {
        // TXT ساده یا SQL
        if ($ext === 'txt') {
            $is_txt = ($ext === 'txt');
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            if (empty($lines)) {
                echo json_encode(["ok" => false, "error" => "فایل خالی است"]);
                exit();
            }


            if ($preview) {
                $lines = array_slice(file($tmp_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), 0, 10);
                $detected = $delimiter ?: detect_delimiter(implode("\n", $lines));
                $sample = [];
                foreach ($lines as $l) {
                    $row = str_getcsv($l, $detected);
                    $row = array_map('trim', $row);
                    if (count($row) >= 1) $sample[] = $row;
                    if (count($sample) >= 5) break;
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
                if (empty(trim($line))) continue;
                $row = str_getcsv($line, $delimiter);
                $row = array_map('trim', $row);
                $line_count++;

                if (count($row) < 1) continue;

                $phone = '';
                $name = 'بینام';

                if ($has_name && count($row) >= 2) {
                    if ($name_first) {
                        $name = $row[0];
                        $phone = $row[1] ?? '';
                    } else {
                        $name = $row[1] ?? 'بینام';
                        $phone = $row[0];
                    }
                } else {
                    $phone = $row[0];
                }

                $phone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($phone) < 10 || strlen($phone) > 15) {
                    $skipped++;
                    $errors[] = "شماره نامعتبر: $phone";
                    continue;
                }

                if ($leads_func->phone_exists_in_project($project_id, $phone)) {
                    $skipped++;
                    continue;
                }

                $leads[] = ['name' => $name, 'phone' => $phone];
            }

            if (!$preview && !empty($leads)) $imported = $leads_func->import($project_id, $leads);
            echo json_encode(["ok" => true, "imported" => $imported, "skipped" => $skipped, "total_lines" => $line_count]);
            exit();
        }

        // SQL / DB
        $inserts = [];
        $current = '';
        $in_string = false;
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '--') || str_starts_with($line, '#')) continue;

            for ($i = 0; $i < strlen($line); $i++) {
                if (!$in_string && ($line[$i] === "'" || $line[$i] === '"')) {
                    $in_string = true;
                } elseif ($in_string && $line[$i] === $line[$i - 1] && $line[$i - 1] !== '\\') {
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
                    foreach ($v[0] as $i => $val) {
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
        if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("JSON نامعتبر");
        $dataRows = is_array($data) && isset($data[0]) ? $data : [$data];
        $headers = $dataRows ? array_keys($dataRows[0]) : [];
    }

    // پیش‌نمایش برای فرمت‌های ساختاریافته
    if ($preview && !empty($headers)) {
        $sample = array_slice($dataRows, 0, 5);
        $sample = array_map('array_values', $sample);
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
            if (isset($values[$idx])) $name_parts[] = trim($values[$idx]);
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

    if (!$preview && !empty($leads)) {
        $imported = $leads_func->import($project_id, $leads);
    }

    echo json_encode([
        "ok" => true,
        "imported" => $imported ?? 0,
        "skipped" => $skipped,
        "total_lines" => $line_count
    ]);
} catch (Exception $e) {
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}

function detect_delimiter($sample)
{
    $delims = [':', ',', ';', '|'];
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

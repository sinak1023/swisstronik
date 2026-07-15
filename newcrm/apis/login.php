<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();
include '../config.php';

$login_function = new Login($db);
$users_function = new Users($db); 

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'نام کاربری و رمز عبور الزامی است.';
        echo json_encode($response);
        exit;
    }

    $user = $users_function->get_user($username);
    if (!$user) {
        $response['message'] = 'نام کاربری یا رمز عبور اشتباه است.';
        echo json_encode($response);
        exit;
    }

    if (strtotime($user['limit_login']) > time()) {
        $response['message'] = 'حساب شما به دلیل تلاش‌های ناموفق محدود شده است. لطفا بعد از یک ساعت دوباره امتحان کنید.';
        echo json_encode($response);
        exit;
    }

    $checked_user = $login_function->check_user_by_pass($username, $password);
    if ($checked_user !== false) {
        $user_id = $checked_user['id'];
        $user_info = $users_function->get_by_id($user_id);

        if ((int)$user_info['status'] > 0) {
            $users_function->update($user_id, [
                "last_login" => date("Y/m/d H:i"),
                "refus_try" => 0,
                "limit_login" => null 
            ]);

            $_SESSION["loginok"] = 'yes';
            $_SESSION["id"] = $user_id;

           /* $mail = new Mail();
            $persian_date = new PersianDate();
            $mail->Send(
                $user_info['email'],
                "ورود به حساب کاربری",
                "یک دستگاه به حساب کاربری شما وارد شد<br> آیپی : " . $_SERVER['REMOTE_ADDR'] . "<br>زمان ورود : " . $persian_date->jdate("Y/m/d H:i") . "<br>اطلاعات مرورگر : " . $_SERVER['HTTP_USER_AGENT'],
                null
            );*/

            $response['success'] = true;
        } else {
            $response['message'] = 'حساب شما غیرفعال است.';
        }
    } else {
        
        $refus_try = (int)$user['refus_try'] + 1;
        $update_data = ['refus_try' => $refus_try];

        if ($refus_try >= 10) {
            $update_data['limit_login'] = date("Y/m/d H:i", strtotime('+1 hour'));
            $response['message'] = 'تلاش‌های ناموفق بیش از حد. حساب شما برای یک ساعت محدود شد.';
        } else {
            $response['message'] = 'نام کاربری یا رمز عبور اشتباه است.';
        }

        $users_function->update($user['id'], $update_data);
    }
}

echo json_encode($response);
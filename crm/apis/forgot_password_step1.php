<?php
header(header: 'Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../boot_session.php';
session_start();

include '../config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';

    $users_function = new users($db);
    $user = $users_function->get_user($username);

    if ($user) {
        
        if (strtotime($user['limit_reset']) > time()) { 
            echo json_encode(['success' => false, 'message' => 'حساب شما محدود شده است. لطفا بعد از یک ساعت دوباره امتحان کنید.']);
        } else {
            $code = rand(100000, 999999);
            $_SESSION['reset_code'] = $code;
            $_SESSION['reset_username'] = $username;

            $mail = new Mail();
            $mail->Send($username, "کد تایید فراموشی رمز عبور", "کد تایید شما: " . $code, null);

            echo json_encode(['success' => true]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
    }
}
?>
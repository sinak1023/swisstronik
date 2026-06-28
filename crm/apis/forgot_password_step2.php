<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

include '../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    $reset_username = isset($_SESSION['reset_username']) ? $_SESSION['reset_username'] : '';

    $users_function = new Users($db); 
    $user = $users_function->get_user($reset_username);

    if ($user) {
        $refus_try_reset = $user['refus_try_reset'] ?? 0; 

        if ($code == $_SESSION['reset_code']) {
            $users_function->update($user['id'], ['refus_try_reset' => 0]);
            echo json_encode(['success' => true]);
        } else {
            $refus_try_reset++;
            $update_data = ['refus_try_reset' => $refus_try_reset];
            $message = 'کد تایید اشتباه است.';
            if ($refus_try_reset >= 10) {
                $update_data['limit_reset'] = date("Y/m/d H:i", strtotime('+1 hour'));
                $message = 'تلاش‌های ناموفق بیش از حد. حساب شما برای یک ساعت محدود شد.';
            }
            $users_function->update($user['id'], $update_data);
            echo json_encode(['success' => false, 'message' => $message]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
    }
}
?>
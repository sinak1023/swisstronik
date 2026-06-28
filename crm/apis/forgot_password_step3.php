<?php
header(header: 'Content-Type: application/json; charset=utf-8');
session_start();

include '../config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $reset_username = isset($_SESSION['reset_username']) ? $_SESSION['reset_username'] : '';

    if ($new_password === $confirm_password) {
        $users_function = new users($db);
        $user = $users_function->get_user($reset_username);
        if ($user) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $users_function->update($user['id'], ['password' => $hashed_password]);

            unset($_SESSION['reset_code']);
            unset($_SESSION['reset_username']);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'رمزهای عبور مطابقت ندارند.']);
    }
}
?>
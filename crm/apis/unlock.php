<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION["id"])) {
    echo json_encode(["ok" => false, "error" => "وارد حساب کاربری شوید"]);
    exit();
}

require_once '../config.php';
$users_function=new Users($db);
$login_function = new Login($db);
$user_info=$users_function->get_by_id($_SESSION["id"]);
$password = $_POST['password'];
$user = $login_function->check_user_by_pass($user_info['email'], $password);
if ($user !== false) {
    echo json_encode(["ok"=>true]);
}else{
    echo json_encode(["ok"=>false,"error"=>"رمز عبور نادرست است"]);

}
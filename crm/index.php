<?php
session_start();
ini_set('display_errors', 0);
require('config.php');
$root = "";
include("header.php");
if ($ex[0] !== "login" && $ex[0] !== "forgot_password") {
    if (!isset($_SESSION["id"])) {
        echo '<script> location.replace("login"); </script>';
    }
}
if ($ex[0] == '/') {
    require 'pages/login.php';
} elseif ($ex[0] == 'apis') {
    $root = 'apis/' . $ex[1] . '.php';
    require 'apis/' . $ex[1] . '.php';
} elseif (file_exists('pages/' . $ex[0] . '.php')) {
    $root = 'pages/' . $ex[0] . '.php';
    $users_function = new Users($db);
    $admin_info = $users_function->get_by_id($_SESSION["id"]);
    $permissions = json_decode($admin_info['permissions'], true) ?? [];
    if ($admin_info['role_id'] > 0) {
        $roles_function = new Roles($db);
        $role = $roles_function->get_by_id($admin_info['role_id']);
        if ($role) {
            $permissions = json_decode($role['permissions'], true) ?? [];
        }
    }
    if ($ex[0] !== "dashboard" && $ex[0] !== "login" && $ex[0] !== "logout" && $ex[0] !== "forgot_password" && $ex[0] !== "reset_password" && $ex[0] !== "profile" && $ex[0] !== "my_sales") {
        if (!isset($_SESSION["id"]) || !in_array($root, $permissions)) {
            $_SESSION['permission_denied'] = true;
            $_SESSION['permission'] = $root;
            echo '<script> location.replace("dashboard"); </script>';
            exit();
        }
    }
    require 'pages/' . $ex[0] . '.php';
} else {
    echo '<script> location.replace("dashboard"); </script>';
    exit();
}
include("footer.php");
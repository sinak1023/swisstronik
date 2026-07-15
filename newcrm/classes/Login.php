<?php

class Login {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function check_user_by_pass($username, $password) {
        $sql = "SELECT * FROM `users` WHERE (`email` = ? OR `phone` = ?) AND status = 1";
        $admin = $this->db->fetch($sql, [$username,$username]);

        if ($admin && password_verify($password, $admin['password'])) {
            return $admin;
        }
        return false;
    }
}

?>
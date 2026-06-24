<?php
session_start();
require_once '../config/config.php';

if (isset($_SESSION['user_id'])) {
    logActivity('LOGOUT', "User {$_SESSION['user_name']} (Admin TU) logout.");
}

session_destroy();
header('Location: ../index.php');
exit;
?>
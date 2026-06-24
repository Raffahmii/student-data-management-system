<?php
session_start();
require_once '../config/config.php';

if (isset($_SESSION['user_id'])) {
    logActivity('LOGOUT', "User {$_SESSION['user_name']} logout.");
}
session_destroy();
header('Location: ../index.php');
exit;
?>
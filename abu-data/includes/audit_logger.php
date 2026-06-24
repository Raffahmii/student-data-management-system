<?php
// includes/audit_logger.php
if (!function_exists('logActivity')) {
    function logActivity($aksi, $deskripsi, $user_id = null) {
        global $pdo;
        if (!$pdo) return false;
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        if (!$user_id) return false;
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, aksi, deskripsi, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $aksi, $deskripsi]);
    }
}
?>
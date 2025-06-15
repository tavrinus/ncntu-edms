<?php
session_start();
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/ncntu_sed/');
define('APP_PATH', ROOT_PATH . 'app/');
define('CONFIG_PATH', ROOT_PATH . 'config/');
define('PUBLIC_PATH', ROOT_PATH . 'public/');
define('UPLOAD_PATH', PUBLIC_PATH . 'uploads/');
define('BASE_URL', '/ncntu_sed/public/');
define('SITE_TITLE', 'NCNTU-EDMS - Система електронного документообігу');
define('ADMIN_EMAIL', 'admin@ncntu.com.ua');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png']);
require_once CONFIG_PATH . 'database.php';
function redirect($path) {
    $url = BASE_URL . $path;
    header("Location: $url");
    exit;
}
function logAction($action, $description, $user_id = null) {
    $conn = getDBConnection();
    if ($user_id === null && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO system_logs (action, description, user_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $action, $description, $user_id, $ip, $timestamp);
    $result = $stmt->execute();
    $stmt->close();
    $conn->close();
    return $result;
}
define('SITE_NAME', 'NCNTU-EDMS');
if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', 'Система електронного документообігу NCNTU-EDMS');
}
function include_file($path) {
    if (file_exists($path)) {
        include $path;
        return true;
    }
    return false;
} 
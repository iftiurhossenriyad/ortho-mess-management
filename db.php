<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
if (session_status() === PHP_SESSION_NONE) session_start();

$host = getenv('ORTHO_DB_HOST') ?: "127.0.0.1";
$port = (int) (getenv('ORTHO_DB_PORT') ?: 3306);
$user = getenv('ORTHO_DB_USER') ?: "root";
$password = getenv('ORTHO_DB_PASSWORD') ?: "";
$dbname = getenv('ORTHO_DB_NAME') ?: "ortho_db";
$is_local = in_array($host, ["127.0.0.1", "localhost"], true);

// Initialize mysqli
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();

if (!$is_local) {
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
}

// Connect to local XAMPP MySQL by default, or Aiven when ORTHO_DB_HOST is set.
$flags = $is_local ? 0 : MYSQLI_CLIENT_SSL;
if (!mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, NULL, $flags)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed. Check the local MySQL service or database settings."]);
    exit();
}

$conn->set_charset("utf8mb4");
$conn->query("ALTER TABLE meals ADD UNIQUE KEY unique_daily_meal (mess_id, user_id, meal_date)");
$conn->query("ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER phone");
$conn->query("ALTER TABLE users ADD COLUMN user_code VARCHAR(30) NULL AFTER id");
$conn->query("ALTER TABLE users ADD UNIQUE KEY unique_user_code (user_code)");
$conn->query("ALTER TABLE messes ADD COLUMN mess_code VARCHAR(30) NULL AFTER id");
$conn->query("ALTER TABLE messes ADD UNIQUE KEY unique_mess_code (mess_code)");
$mess_rows = $conn->query("SELECT id FROM messes WHERE mess_code IS NULL OR mess_code = ''");
if ($mess_rows) {
    $mess_update = $conn->prepare("UPDATE messes SET mess_code = ? WHERE id = ?");
    while ($mess_row = $mess_rows->fetch_assoc()) {
        $mess_code = 'ORTHO-MESS-' . str_pad((string) $mess_row['id'], 4, '0', STR_PAD_LEFT);
        $mess_update->bind_param('si', $mess_code, $mess_row['id']);
        $mess_update->execute();
    }
}
$code_rows = $conn->query("SELECT id, mess_id FROM users WHERE user_code IS NULL OR user_code = ''");
if ($code_rows) {
    $code_update = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ?");
    while ($code_row = $code_rows->fetch_assoc()) {
        $user_code = 'ORTHO-' . (int) $code_row['mess_id'] . '-' . str_pad((string) $code_row['id'], 4, '0', STR_PAD_LEFT);
        $code_update->bind_param('si', $user_code, $code_row['id']);
        $code_update->execute();
    }
}
$default_password = getenv('ORTHO_DEFAULT_PASSWORD') ?: 'Ortho@123';
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);
$password_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE password_hash IS NULL OR password_hash = ''");
$password_update->bind_param('s', $password_hash);
$password_update->execute();

function require_local_user(): array {
    if (empty($_SESSION['local_user_id'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Login required."]);
        exit();
    }
    return [
        "id" => (int) $_SESSION['local_user_id'],
        "mess_id" => (int) $_SESSION['local_mess_id'],
        "role" => $_SESSION['local_role'] ?? 'member'
    ];
}

function require_manager(): array {
    $user = require_local_user();
    if ($user['role'] !== 'manager') {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Manager access required."]);
        exit();
    }
    return $user;
}
?>
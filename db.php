<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/app_config.php';
start_app_session();

try {
    $host = app_config_value('ORTHO_DB_HOST', "127.0.0.1");
    $port = (int) app_config_value('ORTHO_DB_PORT', '3306');
    $user = app_config_value('ORTHO_DB_USER', "root");
    $password = app_config_value('ORTHO_DB_PASSWORD');
    $dbname = app_config_value('ORTHO_DB_NAME', "ortho_mess_management");
    $caFile = app_config_value('ORTHO_DB_CA_CERT');
} catch (UnexpectedValueException $error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server configuration is invalid."]);
    exit();
}
$is_local = in_array($host, ["127.0.0.1", "localhost"], true);

// Initialize mysqli
mysqli_report(MYSQLI_REPORT_OFF);
$conn = mysqli_init();

if (!$is_local) {
    if (!$caFile || !is_file($caFile) || !is_readable($caFile)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Remote database TLS requires ORTHO_DB_CA_CERT to point to a readable trusted CA certificate."]);
        exit();
    }

    if (!mysqli_ssl_set($conn, NULL, NULL, $caFile, NULL, NULL)
        || !mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Remote database TLS certificate verification could not be configured."]);
        exit();
    }
}

// Connect to local XAMPP MySQL by default, or Aiven when ORTHO_DB_HOST is set.
$flags = $is_local ? 0 : MYSQLI_CLIENT_SSL;
if (!mysqli_real_connect($conn, $host, $user, $password, $dbname, $port, NULL, $flags)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed. Check the local MySQL service or database settings."]);
    exit();
}

if (!$conn->set_charset("utf8mb4")) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database character set could not be configured."]);
    exit();
}

function is_valid_iso_date($value): bool {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function normalize_phone(?string $value): string {
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    $value = preg_replace('/[\s\-().+]/', '', $value);
    $value = preg_replace('/\D+/', '', $value);

    return $value;
}

function schema_failure(): void {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database schema setup failed. Check the database permissions and existing data."]);
    exit();
}

function ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
    $check = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$check) schema_failure();
    $check->bind_param('ss', $table, $column);
    if (!$check->execute()) schema_failure();
    if (!$check->get_result()->fetch_assoc()) {
        if (!$conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition")) schema_failure();
    }
}

function ensure_index(mysqli $conn, string $table, string $index, string $definition): void {
    $check = $conn->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?");
    if (!$check) schema_failure();
    $check->bind_param('ss', $table, $index);
    if (!$check->execute()) schema_failure();
    if (!$check->get_result()->fetch_assoc()) {
        if (!$conn->query("ALTER TABLE `$table` ADD $definition")) schema_failure();
    }
}

ensure_column($conn, 'meals', 'breakfast', 'DECIMAL(6, 2) NOT NULL DEFAULT 0');
ensure_column($conn, 'meals', 'lunch', 'DECIMAL(6, 2) NOT NULL DEFAULT 0');
ensure_column($conn, 'meals', 'dinner', 'DECIMAL(6, 2) NOT NULL DEFAULT 0');
ensure_index($conn, 'meals', 'unique_daily_meal', 'UNIQUE KEY `unique_daily_meal` (`mess_id`, `user_id`, `meal_date`)');
ensure_column($conn, 'users', 'password_hash', 'VARCHAR(255) NULL AFTER `phone`');
ensure_column($conn, 'users', 'user_code', 'VARCHAR(30) NULL AFTER `id`');
ensure_index($conn, 'users', 'unique_user_code', 'UNIQUE KEY `unique_user_code` (`user_code`)');
ensure_column($conn, 'messes', 'mess_code', 'VARCHAR(30) NULL AFTER `id`');
ensure_index($conn, 'messes', 'unique_mess_code', 'UNIQUE KEY `unique_mess_code` (`mess_code`)');
$mess_rows = $conn->query("SELECT id FROM messes WHERE mess_code IS NULL OR mess_code = ''");
if (!$mess_rows) schema_failure();
$mess_update = $conn->prepare("UPDATE messes SET mess_code = ? WHERE id = ?");
if (!$mess_update) schema_failure();
while ($mess_row = $mess_rows->fetch_assoc()) {
    $mess_code = 'ORTHO-MESS-' . str_pad((string) $mess_row['id'], 4, '0', STR_PAD_LEFT);
    $mess_update->bind_param('si', $mess_code, $mess_row['id']);
    if (!$mess_update->execute()) schema_failure();
}
$mess_rows->free();
$code_rows = $conn->query("SELECT id, mess_id FROM users WHERE user_code IS NULL OR user_code = ''");
if (!$code_rows) schema_failure();
$code_update = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ?");
if (!$code_update) schema_failure();
while ($code_row = $code_rows->fetch_assoc()) {
    $user_code = 'ORTHO-' . (int) $code_row['mess_id'] . '-' . str_pad((string) $code_row['id'], 4, '0', STR_PAD_LEFT);
    $code_update->bind_param('si', $user_code, $code_row['id']);
    if (!$code_update->execute()) schema_failure();
}
$code_rows->free();
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
    if ($user['role'] !== 'manager' || !empty($_SESSION['is_impersonating'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Manager access required."]);
        exit();
    }
    return $user;
}
?>
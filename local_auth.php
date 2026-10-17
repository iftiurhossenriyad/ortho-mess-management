<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Use POST.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON request body.']);
    exit();
}
$action = $data['action'] ?? '';
if (!is_string($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid authentication action.']);
    exit();
}

if ($action === 'login') {
    $mess_value = $data['mess_id'] ?? '';
    $phone_value = $data['phone'] ?? '';
    $password = $data['password'] ?? '';
    if (!is_string($mess_value) || !is_string($phone_value) || !is_string($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Enter valid sign-in details.']);
        exit();
    }
    $mess_identifier = strtoupper(trim($mess_value));
    $phone = normalize_phone($phone_value);
    if (!$mess_identifier || !$phone || !$password) {
        echo json_encode(['status' => 'error', 'message' => 'Mess ID, phone number, and password are required.']);
        exit();
    }
    $login_id = strtoupper(trim((string) $phone_value));
    $stmt = $conn->prepare("SELECT u.id, u.mess_id, m.mess_code, u.name, u.phone, u.role, u.password_hash FROM users u JOIN messes m ON m.id = u.mess_id WHERE (m.mess_code = ? OR CAST(m.id AS CHAR) = ?) AND (u.user_code = ? OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ?) LIMIT 1");
    $stmt->bind_param('ssss', $mess_identifier, $mess_identifier, $login_id, $phone);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Mess ID/code, User ID/phone, or password.']);
        exit();
    }
    session_regenerate_id(true);
    unset($_SESSION['admin_user_id'], $_SESSION['admin_mess_id'], $_SESSION['admin_role'], $_SESSION['admin_name'], $_SESSION['admin_mess_code'], $_SESSION['is_impersonating']);
    $_SESSION['local_user_id'] = (int) $user['id'];
    $_SESSION['local_mess_id'] = (int) $user['mess_id'];
    $_SESSION['local_mess_code'] = $user['mess_code'];
    $_SESSION['local_role'] = $user['role'];
    $_SESSION['local_name'] = $user['name'];
    echo json_encode(['status' => 'success', 'data' => ['id' => (int) $user['id'], 'name' => $user['name'], 'role' => $user['role'], 'mess_id' => (int) $user['mess_id'], 'mess_code' => $user['mess_code']]]);
    exit();
}

if ($action === 'session') {
    if (empty($_SESSION['local_user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Not signed in.']);
        exit();
    }
    echo json_encode(['status' => 'success', 'data' => ['id' => $_SESSION['local_user_id'], 'name' => $_SESSION['local_name'], 'role' => $_SESSION['local_role'], 'mess_id' => $_SESSION['local_mess_id'], 'mess_code' => $_SESSION['local_mess_code'] ?? null, 'is_impersonating' => !empty($_SESSION['is_impersonating'])]]);
    exit();
}

if ($action === 'switch_user') {
    $auth_user = require_local_user();
    if ($auth_user['role'] !== 'manager' && empty($_SESSION['admin_user_id'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only the manager can switch users.']);
        exit();
    }
    if (empty($_SESSION['admin_user_id'])) {
        $_SESSION['admin_user_id'] = $_SESSION['local_user_id'];
        $_SESSION['admin_mess_id'] = $_SESSION['local_mess_id'];
        $_SESSION['admin_role'] = $_SESSION['local_role'];
        $_SESSION['admin_name'] = $_SESSION['local_name'];
        $_SESSION['admin_mess_code'] = $_SESSION['local_mess_code'] ?? null;
    }
    $target_id = (int) ($data['user_id'] ?? 0);
    $target = $conn->prepare('SELECT id, mess_id, name, role FROM users WHERE id = ? AND mess_id = ?');
    $target->bind_param('ii', $target_id, $_SESSION['admin_mess_id']);
    $target->execute();
    $target_user = $target->get_result()->fetch_assoc();
    if (!$target_user) {
        echo json_encode(['status' => 'error', 'message' => 'Selected user is not in this mess.']);
        exit();
    }
    $_SESSION['local_user_id'] = (int) $target_user['id'];
    $_SESSION['local_mess_id'] = (int) $target_user['mess_id'];
    $_SESSION['local_role'] = $target_user['role'];
    $_SESSION['local_name'] = $target_user['name'];
    $_SESSION['is_impersonating'] = true;
    echo json_encode(['status' => 'success', 'message' => 'Now viewing this user dashboard.']);
    exit();
}

if ($action === 'exit_impersonation') {
    if (empty($_SESSION['admin_user_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'No admin session to restore.']);
        exit();
    }
    $_SESSION['local_user_id'] = $_SESSION['admin_user_id'];
    $_SESSION['local_mess_id'] = $_SESSION['admin_mess_id'];
    $_SESSION['local_role'] = $_SESSION['admin_role'];
    $_SESSION['local_name'] = $_SESSION['admin_name'];
    $_SESSION['local_mess_code'] = $_SESSION['admin_mess_code'];
    unset($_SESSION['admin_user_id'], $_SESSION['admin_mess_id'], $_SESSION['admin_role'], $_SESSION['admin_name'], $_SESSION['admin_mess_code'], $_SESSION['is_impersonating']);
    echo json_encode(['status' => 'success', 'message' => 'Returned to admin dashboard.']);
    exit();
}

if ($action === 'change_password') {
    if (!empty($_SESSION['is_impersonating'])) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Return to your own account before changing a password.']);
        exit();
    }
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    if (!is_string($current_password) || !is_string($new_password)
        || strlen($new_password) < 8 || strlen($new_password) > 255) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'New password must be between 8 and 255 characters.']);
        exit();
    }
    $auth_user = require_local_user();
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? AND mess_id = ?');
    $stmt->bind_param('ii', $auth_user['id'], $auth_user['mess_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user || empty($user['password_hash']) || !password_verify($current_password, $user['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
        exit();
    }
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND mess_id = ?');
    $update->bind_param('sii', $new_hash, $auth_user['id'], $auth_user['mess_id']);
    if (!$update->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Password could not be changed. Please try again.']);
        exit();
    }
    echo json_encode(['status' => 'success', 'message' => 'Your password was changed successfully.']);
    exit();
}

if ($action === 'admin_reset_password') {
    $auth_user = require_manager();
    $target_id = (int) ($data['user_id'] ?? 0);
    if (!$target_id) {
        echo json_encode(['status' => 'error', 'message' => 'Select a member first.']);
        exit();
    }
    $target = $conn->prepare('SELECT id, name FROM users WHERE id = ? AND mess_id = ?');
    $target->bind_param('ii', $target_id, $auth_user['mess_id']);
    $target->execute();
    $target_user = $target->get_result()->fetch_assoc();
    if (!$target_user) {
        echo json_encode(['status' => 'error', 'message' => 'Selected user is not in this mess.']);
        exit();
    }
    $temporary_password = 'Ortho-' . strtoupper(bin2hex(random_bytes(8)));
    $temporary_hash = password_hash($temporary_password, PASSWORD_DEFAULT);
    $update = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND mess_id = ?');
    $update->bind_param('sii', $temporary_hash, $target_id, $auth_user['mess_id']);
    if (!$update->execute()) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Temporary password could not be generated. Please try again.']);
        exit();
    }
    echo json_encode(['status' => 'success', 'message' => 'Temporary password generated.', 'data' => ['name' => $target_user['name'], 'temporary_password' => $temporary_password]]);
    exit();
}

if ($action === 'logout') {
    session_unset();
    session_destroy();
    echo json_encode(['status' => 'success']);
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Invalid auth action.']);
?>
<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $session_user = require_local_user();
    $mess_id = $_GET['mess_id'] ?? null;

    if (!$mess_id) {
        echo json_encode(["status" => "error", "message" => "Mess ID is required!"]);
        exit();
    }
    if ($session_user['mess_id'] !== (int) $mess_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit();
    }

    $user_filter = $session_user['role'] === 'manager' ? '' : ' AND id = ?';
    $stmt = $conn->prepare("SELECT id, user_code, name, phone, role, created_at FROM users WHERE mess_id = ?$user_filter");
    if ($session_user['role'] === 'manager') $stmt->bind_param("i", $mess_id);
    else $stmt->bind_param("ii", $mess_id, $session_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "data" => $users
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use GET."]);
}
?>
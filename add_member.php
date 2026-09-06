<?php
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_user = require_manager();
    $mess_id = $data['mess_id'] ?? null;
    $name = $data['name'] ?? null;
    $phone = $data['phone'] ?? '';
    $role = $data['role'] ?? 'member';

    if (!$mess_id || !$name) {
        echo json_encode(["status" => "error", "message" => "Mess ID and Name are required!"]);
        exit();
    }
    if ($session_user['mess_id'] !== (int) $mess_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $mess_id, $name, $phone, $role);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $new_user_id, 4, '0', STR_PAD_LEFT);
        $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
        $code_stmt->bind_param("sii", $user_code, $new_user_id, $mess_id);
        $code_stmt->execute();
        echo json_encode([
            "status" => "success",
            "message" => "Member added successfully!",
            "data" => [
                "user_id" => $new_user_id,
                "user_code" => $user_code,
                "name" => $name,
                "phone" => $phone,
                "role" => $role
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add member."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
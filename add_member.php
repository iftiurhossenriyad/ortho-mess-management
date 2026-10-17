<?php
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_user = require_manager();
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON request body."]);
        exit();
    }
    $mess_id = $data['mess_id'] ?? null;
    $name_input = $data['name'] ?? '';
    $phone_input = $data['phone'] ?? '';
    if (!is_string($name_input) || !is_string($phone_input)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Enter a valid name and phone number."]);
        exit();
    }
    $name = trim($name_input);
    $phone = normalize_phone($phone_input);

    if (!$mess_id || $name === '' || $phone === '' || strlen($name) > 100 || strlen($phone) > 30) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Name and phone number are required."]);
        exit();
    }
    if ($session_user['mess_id'] !== (int) $mess_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit();
    }

    $existing_member = $conn->prepare("SELECT id FROM users WHERE mess_id = ? AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ? LIMIT 1");
    $existing_member->bind_param("is", $mess_id, $phone);
    $existing_member->execute();
    if ($existing_member->get_result()->fetch_assoc()) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "A member with this phone number already exists in this mess."]);
        exit();
    }

    $temporary_password = 'Ortho-' . strtoupper(bin2hex(random_bytes(8)));
    $password_hash = password_hash($temporary_password, PASSWORD_DEFAULT);
    $role = 'member';
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $mess_id, $name, $phone, $password_hash, $role);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $new_user_id, 4, '0', STR_PAD_LEFT);
        $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
        $code_stmt->bind_param("sii", $user_code, $new_user_id, $mess_id);
        if (!$code_stmt->execute()) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Member was not added. Please try again."]);
            exit();
        }
        $conn->commit();
        echo json_encode([
            "status" => "success",
            "message" => "Member added successfully!",
            "data" => [
                "user_id" => $new_user_id,
                "user_code" => $user_code,
                "name" => $name,
                "phone" => $phone,
                "role" => $role,
                "temporary_password" => $temporary_password
            ]
        ]);
    } else {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Failed to add member."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
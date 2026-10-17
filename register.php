<?php
require_once 'db.php';

// Get JSON input from request body
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON request body."]);
        exit();
    }
    $name_input = $data['name'] ?? '';
    $phone_input = $data['phone'] ?? '';
    $password_input = $data['password'] ?? '';
    $action = $data['action'] ?? '';
    if (!is_string($name_input) || !is_string($phone_input) || !is_string($password_input)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Enter valid account details."]);
        exit();
    }
    $name = trim($name_input);
    $phone = normalize_phone($phone_input);
    $password = $password_input;

    if ($name === '' || strlen($name) > 100 || $phone === '' || strlen($phone) > 30
        || strlen($password) < 8 || strlen($password) > 255
        || !in_array($action, ['create_mess', 'join_mess'], true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Enter a valid name, phone number, action, and a password of 8 to 255 characters."]);
        exit();
    }

    if ($action === 'create_mess') {
        $mess_name_input = $data['mess_name'] ?? '';
        if (!is_string($mess_name_input)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Enter a valid mess name."]);
            exit();
        }
        $mess_name = trim($mess_name_input);
        if ($mess_name === '' || strlen($mess_name) > 100) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Mess name is required!"]);
            exit();
        }

        $invite_code = strtoupper(bin2hex(random_bytes(12)));

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO messes (mess_name, invite_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $mess_name, $invite_code);

        if ($stmt->execute()) {
            $mess_id = $stmt->insert_id;
            $mess_code = 'ORTHO-MESS-' . str_pad((string) $mess_id, 4, '0', STR_PAD_LEFT);
            $mess_code_stmt = $conn->prepare("UPDATE messes SET mess_code = ? WHERE id = ?");
            $mess_code_stmt->bind_param("si", $mess_code, $mess_id);
            if (!$mess_code_stmt->execute()) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Mess setup failed. Please try again."]);
                exit();
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, password_hash, role) VALUES (?, ?, ?, ?, 'manager')");
            $user_stmt->bind_param("isss", $mess_id, $name, $phone, $password_hash);
            if (!$user_stmt->execute()) {
                $conn->rollback();
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Account could not be created. The phone number may already be registered."]);
                exit();
            }
            $user_id = $user_stmt->insert_id;
            $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $user_id, 4, '0', STR_PAD_LEFT);
            $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
            $code_stmt->bind_param("sii", $user_code, $user_id, $mess_id);
            if (!$code_stmt->execute()) {
                $conn->rollback();
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Account setup failed. Please try again."]);
                exit();
            }
            $conn->commit();

            echo json_encode([
                "status" => "success",
                "message" => "Mess created successfully!",
                "data" => [
                    "user_id" => $user_id,
                    "user_code" => $user_code,
                    "mess_id" => $mess_id,
                    "mess_code" => $mess_code,
                    "mess_name" => $mess_name,
                    "invite_code" => $invite_code,
                    "role" => "manager"
                ]
            ]);
        } else {
            $conn->rollback();
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Mess could not be created. Please try again."]);
        }

    } elseif ($action === 'join_mess') {
        $invite_code_input = $data['invite_code'] ?? '';
        if (!is_string($invite_code_input)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Enter a valid invite code."]);
            exit();
        }
        $invite_code = strtoupper(trim($invite_code_input));
        if ($invite_code === '' || strlen($invite_code) > 30) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invite code is required!"]);
            exit();
        }

        // Find Mess by invite code
        $stmt = $conn->prepare("SELECT id, mess_name, mess_code FROM messes WHERE invite_code = ?");
        $stmt->bind_param("s", $invite_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $mess = $result->fetch_assoc();
            $mess_id = $mess['id'];

            $existing_user = $conn->prepare("SELECT id FROM users WHERE mess_id = ? AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ? LIMIT 1");
            $existing_user->bind_param("is", $mess_id, $phone);
            $existing_user->execute();
            if ($existing_user->get_result()->fetch_assoc()) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "This phone number already has an account in that mess."]);
                exit();
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            $user_stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, password_hash, role) VALUES (?, ?, ?, ?, 'member')");
            $user_stmt->bind_param("isss", $mess_id, $name, $phone, $password_hash);

            if ($user_stmt->execute()) {
                $user_id = $user_stmt->insert_id;
                $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $user_id, 4, '0', STR_PAD_LEFT);
                $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
                $code_stmt->bind_param("sii", $user_code, $user_id, $mess_id);
                if (!$code_stmt->execute()) {
                    $conn->rollback();
                    http_response_code(500);
                    echo json_encode(["status" => "error", "message" => "Account setup failed. Please try again."]);
                    exit();
                }
                $conn->commit();
                echo json_encode([
                    "status" => "success",
                    "message" => "Joined mess successfully!",
                    "data" => [
                        "user_id" => $user_id,
                        "user_code" => $user_code,
                        "mess_id" => $mess_id,
                        "mess_code" => $mess['mess_code'],
                        "mess_name" => $mess['mess_name'],
                        "role" => "member"
                    ]
                ]);
            } else {
                $conn->rollback();
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Failed to join mess or phone number already exists."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid invite code!"]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
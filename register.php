<?php
require_once 'db.php';

// Get JSON input from request body
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $data['name'] ?? null;
    $phone = $data['phone'] ?? null;
    $password = $data['password'] ?? '';
    $action = $data['action'] ?? null; // 'create_mess' or 'join_mess'
    
    if (!$name || !$phone || !$action || strlen($password) < 8) {
        echo json_encode(["status" => "error", "message" => "Name, phone, action, and a password of at least 8 characters are required!"]);
        exit();
    }

    if ($action === 'create_mess') {
        $mess_name = $data['mess_name'] ?? null;
        if (!$mess_name) {
            echo json_encode(["status" => "error", "message" => "Mess name is required!"]);
            exit();
        }

        // Generate unique 6-character invite code
        $invite_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        // Insert new Mess
        $stmt = $conn->prepare("INSERT INTO messes (mess_name, invite_code) VALUES (?, ?)");
        $stmt->bind_param("ss", $mess_name, $invite_code);
        
        if ($stmt->execute()) {
            $mess_id = $stmt->insert_id;
            $mess_code = 'ORTHO-MESS-' . str_pad((string) $mess_id, 4, '0', STR_PAD_LEFT);
            $mess_code_stmt = $conn->prepare("UPDATE messes SET mess_code = ? WHERE id = ?");
            $mess_code_stmt->bind_param("si", $mess_code, $mess_id);
            $mess_code_stmt->execute();

            // Insert User as Manager
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, password_hash, role) VALUES (?, ?, ?, ?, 'manager')");
            $user_stmt->bind_param("isss", $mess_id, $name, $phone, $password_hash);
            $user_stmt->execute();
            $user_id = $user_stmt->insert_id;
            $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $user_id, 4, '0', STR_PAD_LEFT);
            $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
            $code_stmt->bind_param("sii", $user_code, $user_id, $mess_id);
            $code_stmt->execute();

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
            echo json_encode(["status" => "error", "message" => "Failed to create mess."]);
        }

    } elseif ($action === 'join_mess') {
        $invite_code = $data['invite_code'] ?? null;
        if (!$invite_code) {
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

            // Insert User as Member
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user_stmt = $conn->prepare("INSERT INTO users (mess_id, name, phone, password_hash, role) VALUES (?, ?, ?, ?, 'member')");
            $user_stmt->bind_param("isss", $mess_id, $name, $phone, $password_hash);
            
            if ($user_stmt->execute()) {
                $user_id = $user_stmt->insert_id;
                $user_code = 'ORTHO-' . (int) $mess_id . '-' . str_pad((string) $user_id, 4, '0', STR_PAD_LEFT);
                $code_stmt = $conn->prepare("UPDATE users SET user_code = ? WHERE id = ? AND mess_id = ?");
                $code_stmt->bind_param("sii", $user_code, $user_id, $mess_id);
                $code_stmt->execute();
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
                echo json_encode(["status" => "error", "message" => "Failed to join mess or phone number already exists."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid invite code!"]);
        }
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
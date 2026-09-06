<?php
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_user = require_manager();
    $mess_id = $data['mess_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $type = $data['type'] ?? null; // 'bazaar', 'utility', or 'deposit'
    $amount = $data['amount'] ?? null;
    $title = $data['title'] ?? '';
    $transaction_date = $data['transaction_date'] ?? date('Y-m-d');

    if (!$mess_id || !$user_id || !$type || !is_numeric($amount) || $amount <= 0) {
        echo json_encode(["status" => "error", "message" => "Mess ID, User ID, Type, and Amount are required!"]);
        exit();
    }
    if ($session_user['mess_id'] !== (int) $mess_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit();
    }

    if (!in_array($type, ['bazaar', 'utility', 'deposit'], true)
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $transaction_date)) {
        echo json_encode(["status" => "error", "message" => "Invalid transaction type or date."]);
        exit();
    }

    $member_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND mess_id = ?");
    $member_stmt->bind_param("ii", $user_id, $mess_id);
    $member_stmt->execute();
    if (!$member_stmt->get_result()->fetch_assoc()) {
        echo json_encode(["status" => "error", "message" => "Selected member does not belong to this mess."]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO transactions (mess_id, user_id, type, amount, title, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisdss", $mess_id, $user_id, $type, $amount, $title, $transaction_date);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Transaction recorded successfully!",
            "data" => [
                "transaction_id" => $stmt->insert_id,
                "mess_id" => $mess_id,
                "user_id" => $user_id,
                "type" => $type,
                "amount" => $amount,
                "title" => $title,
                "date" => $transaction_date
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to record transaction."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
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
    $user_filter = $session_user['role'] === 'manager' ? '' : ' AND t.user_id = ?';

    $stmt = $conn->prepare("
        SELECT t.id, t.type, t.amount, t.title, t.transaction_date, t.created_at, u.name as user_name 
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.mess_id = ?$user_filter
        ORDER BY t.transaction_date DESC, t.created_at DESC
        LIMIT 10
    ");
    if ($session_user['role'] === 'manager') $stmt->bind_param("i", $mess_id);
    else $stmt->bind_param("ii", $mess_id, $session_user['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $transactions]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method!"]);
}
?>
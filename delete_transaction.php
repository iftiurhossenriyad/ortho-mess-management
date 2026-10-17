<?php
require_once 'db.php';
$session_user = require_manager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $tx_id = $data['id'] ?? null;

    if (!$tx_id) {
        echo json_encode(["status" => "error", "message" => "Transaction ID is required!"]);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND mess_id = ?");
    $stmt->bind_param("ii", $tx_id, $session_user['mess_id']);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Transaction deleted successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete transaction!"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method!"]);
}
?>
<?php
require_once 'db.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_user = require_manager();
    $mess_id = $data['mess_id'] ?? null;
    $user_id = $data['user_id'] ?? null;
    $meal_date = $data['meal_date'] ?? date('Y-m-d');
    $breakfast = $data['breakfast'] ?? 0.0;
    $lunch = $data['lunch'] ?? 0.0;
    $dinner = $data['dinner'] ?? 0.0;

    if (!$mess_id || !$user_id) {
        echo json_encode(["status" => "error", "message" => "Mess ID and User ID are required!"]);
        exit();
    }
    if ($session_user['mess_id'] !== (int) $mess_id) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied."]);
        exit();
    }

    if (!is_numeric($breakfast) || !is_numeric($lunch) || !is_numeric($dinner)
        || $breakfast < 0 || $lunch < 0 || $dinner < 0) {
        echo json_encode(["status" => "error", "message" => "Use valid non-negative meal values."]);
        exit();
    }

    $member_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND mess_id = ?");
    $member_stmt->bind_param("ii", $user_id, $mess_id);
    $member_stmt->execute();
    if (!$member_stmt->get_result()->fetch_assoc()) {
        echo json_encode(["status" => "error", "message" => "Selected member does not belong to this mess."]);
        exit();
    }

    // Insert or Update daily meal using MySQL ON DUPLICATE KEY UPDATE
    $stmt = $conn->prepare("
        INSERT INTO meals (mess_id, user_id, meal_date, breakfast, lunch, dinner) 
        VALUES (?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        breakfast = VALUES(breakfast), 
        lunch = VALUES(lunch), 
        dinner = VALUES(dinner)
    ");

    $stmt->bind_param("iisddd", $mess_id, $user_id, $meal_date, $breakfast, $lunch, $dinner);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Meal updated successfully!",
            "data" => [
                "user_id" => $user_id,
                "meal_date" => $meal_date,
                "breakfast" => $breakfast,
                "lunch" => $lunch,
                "dinner" => $dinner
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update meal."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use POST."]);
}
?>
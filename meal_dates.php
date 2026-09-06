<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use GET."]);
    exit();
}
$session_user = require_local_user();

$mess_id = filter_input(INPUT_GET, 'mess_id', FILTER_VALIDATE_INT);
if (!$mess_id) {
    echo json_encode(["status" => "error", "message" => "Mess ID is required!"]);
    exit();
}

if ($session_user['mess_id'] !== $mess_id) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit();
}
$user_filter = $session_user['role'] === 'manager' ? '' : ' AND user_id = ?';
$stmt = $conn->prepare("\n    SELECT meal_date, SUM(breakfast + lunch + dinner) AS total_meals,\n           COUNT(DISTINCT user_id) AS member_count\n    FROM meals\n    WHERE mess_id = ?$user_filter\n    GROUP BY meal_date\n    ORDER BY meal_date DESC\n");
if ($session_user['role'] === 'manager') $stmt->bind_param("i", $mess_id);
else $stmt->bind_param("ii", $mess_id, $session_user['id']);
$stmt->execute();
$result = $stmt->get_result();

$dates = [];
while ($row = $result->fetch_assoc()) {
    $dates[] = [
        "meal_date" => $row['meal_date'],
        "total_meals" => (float) $row['total_meals'],
        "member_count" => (int) $row['member_count']
    ];
}

echo json_encode(["status" => "success", "data" => $dates]);
?>
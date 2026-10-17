<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["status" => "error", "message" => "Invalid request method! Use GET."]);
    exit();
}

$mess_id = filter_input(INPUT_GET, 'mess_id', FILTER_VALIDATE_INT);
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$session_user = require_local_user();
$meal_date = $_GET['meal_date'] ?? date('Y-m-d');

if (!$mess_id) {
    echo json_encode(["status" => "error", "message" => "Valid Mess ID and date are required!"]);
    exit();
}
if ($session_user['mess_id'] !== $mess_id) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied."]);
    exit();
}
if ($session_user['role'] !== 'manager') $user_id = $session_user['id'];

if ($user_id) {
    $member_stmt = $conn->prepare("SELECT id, name, phone FROM users WHERE id = ? AND mess_id = ?");
    $member_stmt->bind_param("ii", $user_id, $mess_id);
    $member_stmt->execute();
    $member = $member_stmt->get_result()->fetch_assoc();

    if (!$member) {
        echo json_encode(["status" => "error", "message" => "Selected member does not belong to this mess."]);
        exit();
    }

    $stmt = $conn->prepare("\n        SELECT meal_date,\n               COALESCE(breakfast, 0) AS breakfast,\n               COALESCE(lunch, 0) AS lunch,\n               COALESCE(dinner, 0) AS dinner\n        FROM meals\n        WHERE mess_id = ? AND user_id = ?\n        ORDER BY meal_date DESC\n    ");
    $stmt->bind_param("ii", $mess_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $daily = [];
    $summary = [
        "total_meals" => 0.0,
        "days_recorded" => 0,
        "days_with_meals" => 0,
        "days_without_meals" => 0,
        "breakfast_count" => 0.0,
        "lunch_count" => 0.0,
        "dinner_count" => 0.0,
        "breakfast_missed" => 0,
        "lunch_missed" => 0,
        "dinner_missed" => 0
    ];

    while ($row = $result->fetch_assoc()) {
        $breakfast = (float) $row['breakfast'];
        $lunch = (float) $row['lunch'];
        $dinner = (float) $row['dinner'];
        $total = $breakfast + $lunch + $dinner;

        $daily[] = [
            "meal_date" => $row['meal_date'],
            "breakfast" => $breakfast,
            "lunch" => $lunch,
            "dinner" => $dinner,
            "total" => $total,
            "breakfast_status" => $breakfast > 0 ? "Had" : "Did not eat",
            "lunch_status" => $lunch > 0 ? "Had" : "Did not eat",
            "dinner_status" => $dinner > 0 ? "Had" : "Did not eat",
            "status" => $total > 0 ? "Had meal" : "No meal"
        ];

        $summary['total_meals'] += $total;
        $summary['days_recorded']++;
        $summary['days_with_meals'] += $total > 0 ? 1 : 0;
        $summary['days_without_meals'] += $total === 0.0 ? 1 : 0;
        $summary['breakfast_count'] += $breakfast;
        $summary['lunch_count'] += $lunch;
        $summary['dinner_count'] += $dinner;
        $summary['breakfast_missed'] += $breakfast === 0.0 ? 1 : 0;
        $summary['lunch_missed'] += $lunch === 0.0 ? 1 : 0;
        $summary['dinner_missed'] += $dinner === 0.0 ? 1 : 0;
    }

    echo json_encode([
        "status" => "success",
        "member" => [
            "id" => (int) $member['id'],
            "name" => $member['name'],
            "phone" => $member['phone']
        ],
        "summary" => $summary,
        "data" => $daily
    ]);
    exit();
}

if ($meal_date === 'all') {
    $stmt = $conn->prepare("\n        SELECT m.meal_date, u.id, u.name,\n               COALESCE(m.breakfast, 0) AS breakfast,\n               COALESCE(m.lunch, 0) AS lunch,\n               COALESCE(m.dinner, 0) AS dinner\n        FROM meals m\n        JOIN users u ON u.id = m.user_id AND u.mess_id = m.mess_id\n        WHERE m.mess_id = ?\n        ORDER BY m.meal_date DESC, u.name\n    ");
    $stmt->bind_param("i", $mess_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = [
            "id" => (int) $row['id'],
            "name" => $row['name'],
            "meal_date" => $row['meal_date'],
            "breakfast" => (float) $row['breakfast'],
            "lunch" => (float) $row['lunch'],
            "dinner" => (float) $row['dinner']
        ];
    }
    echo json_encode(["status" => "success", "date" => "all", "data" => $history]);
    exit();
}

if (!is_valid_iso_date($meal_date)) {
    echo json_encode(["status" => "error", "message" => "Valid Mess ID and date are required!"]);
    exit();
}

$stmt = $conn->prepare("
    SELECT u.id, u.name,
           COALESCE(m.breakfast, 0) AS breakfast,
           COALESCE(m.lunch, 0) AS lunch,
           COALESCE(m.dinner, 0) AS dinner
    FROM users u
    LEFT JOIN meals m ON m.user_id = u.id
        AND m.mess_id = u.mess_id
        AND m.meal_date = ?
    WHERE u.mess_id = ?
    ORDER BY u.name
");
$stmt->bind_param("si", $meal_date, $mess_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        "id" => (int) $row['id'],
        "name" => $row['name'],
        "breakfast" => (float) $row['breakfast'],
        "lunch" => (float) $row['lunch'],
        "dinner" => (float) $row['dinner']
    ];
}

echo json_encode([
    "status" => "success",
    "date" => $meal_date,
    "data" => $history
]);
?>
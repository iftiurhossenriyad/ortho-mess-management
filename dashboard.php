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
    $user_filter = $session_user['role'] === 'manager' ? '' : ' AND user_id = ?';

    // Total Meals
    $stmt1 = $conn->prepare("SELECT SUM(breakfast + lunch + dinner) as total_meals FROM meals WHERE mess_id = ?$user_filter");
    if ($session_user['role'] === 'manager') $stmt1->bind_param("i", $mess_id);
    else $stmt1->bind_param("ii", $mess_id, $session_user['id']);
    $stmt1->execute();
    $total_meals = $stmt1->get_result()->fetch_assoc()['total_meals'] ?? 0;

    // Total Bazaar Expense
    $stmt2 = $conn->prepare("SELECT SUM(amount) as total_bazaar FROM transactions WHERE mess_id = ? AND type = 'bazaar'$user_filter");
    if ($session_user['role'] === 'manager') $stmt2->bind_param("i", $mess_id);
    else $stmt2->bind_param("ii", $mess_id, $session_user['id']);
    $stmt2->execute();
    $total_bazaar = $stmt2->get_result()->fetch_assoc()['total_bazaar'] ?? 0;

    // Total Utility Expense
    $stmt3 = $conn->prepare("SELECT SUM(amount) as total_utility FROM transactions WHERE mess_id = ? AND type = 'utility'$user_filter");
    if ($session_user['role'] === 'manager') $stmt3->bind_param("i", $mess_id);
    else $stmt3->bind_param("ii", $mess_id, $session_user['id']);
    $stmt3->execute();
    $total_utility = $stmt3->get_result()->fetch_assoc()['total_utility'] ?? 0;

    // Total Expense
    $total_expense = $total_bazaar + $total_utility;

    // Total Deposit
    $stmt4 = $conn->prepare("SELECT SUM(amount) as total_deposit FROM transactions WHERE mess_id = ? AND type = 'deposit'$user_filter");
    if ($session_user['role'] === 'manager') $stmt4->bind_param("i", $mess_id);
    else $stmt4->bind_param("ii", $mess_id, $session_user['id']);
    $stmt4->execute();
    $total_deposit = $stmt4->get_result()->fetch_assoc()['total_deposit'] ?? 0;

    // Meal rate is shared by the whole mess, even in a member's personal view.
    $rate_stmt = $conn->prepare("SELECT
        (SELECT COALESCE(SUM(breakfast + lunch + dinner), 0) FROM meals WHERE mess_id = ?) AS mess_meals,
        (SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE mess_id = ? AND type = 'bazaar') AS mess_bazaar");
    $rate_stmt->bind_param("ii", $mess_id, $mess_id);
    $rate_stmt->execute();
    $rate_totals = $rate_stmt->get_result()->fetch_assoc();
    $meal_rate = ($rate_totals['mess_meals'] > 0)
        ? ($rate_totals['mess_bazaar'] / $rate_totals['mess_meals'])
        : 0;

    echo json_encode([
        "status" => "success",
        "data" => [
            "total_meals" => (float)$total_meals,
            "total_expense" => round($total_expense, 2),
            "total_bazaar" => round($total_bazaar, 2),
            "total_utility" => round($total_utility, 2),
            "total_deposit" => round($total_deposit, 2),
            "meal_rate" => round($meal_rate, 2)
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method!"]);
}
?>
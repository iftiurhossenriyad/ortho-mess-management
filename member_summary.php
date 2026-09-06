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

    // 1. Total Bazaar Expense
    $stmt1 = $conn->prepare("SELECT SUM(amount) as total_bazaar FROM transactions WHERE mess_id = ? AND type = 'bazaar'");
    $stmt1->bind_param("i", $mess_id);
    $stmt1->execute();
    $total_bazaar = $stmt1->get_result()->fetch_assoc()['total_bazaar'] ?? 0;

    // 2. Total Utility Expense (Fixed costs to be split equally)
    $stmt_u = $conn->prepare("SELECT SUM(amount) as total_utility FROM transactions WHERE mess_id = ? AND type = 'utility'");
    $stmt_u->bind_param("i", $mess_id);
    $stmt_u->execute();
    $total_utility = $stmt_u->get_result()->fetch_assoc()['total_utility'] ?? 0;

    // 3. Total Meals
    $stmt2 = $conn->prepare("SELECT SUM(breakfast + lunch + dinner) as total_meals FROM meals WHERE mess_id = ?");
    $stmt2->bind_param("i", $mess_id);
    $stmt2->execute();
    $total_meals = $stmt2->get_result()->fetch_assoc()['total_meals'] ?? 0;

    // 4. Meal Rate (Calculated from Bazaar Expense only)
    $meal_rate = ($total_meals > 0) ? ($total_bazaar / $total_meals) : 0;

    // 5. Fetch Total Members Count for Utility split
    $stmt_members = $conn->prepare("SELECT COUNT(*) as member_count FROM users WHERE mess_id = ?");
    $stmt_members->bind_param("i", $mess_id);
    $stmt_members->execute();
    $member_count = $stmt_members->get_result()->fetch_assoc()['member_count'] ?? 1;

    $per_member_utility = ($member_count > 0) ? ($total_utility / $member_count) : 0;

    // 6. Fetch Users Summary
    $user_filter = $session_user['role'] === 'manager' ? '' : ' AND id = ?';
    $stmt3 = $conn->prepare("SELECT id, name FROM users WHERE mess_id = ?$user_filter");
    if ($session_user['role'] === 'manager') $stmt3->bind_param("i", $mess_id);
    else $stmt3->bind_param("ii", $mess_id, $session_user['id']);
    $stmt3->execute();
    $users_result = $stmt3->get_result();

    $summary = [];

    while ($user = $users_result->fetch_assoc()) {
        $user_id = $user['id'];

        // User Meals
        $stmt_m = $conn->prepare("SELECT SUM(breakfast + lunch + dinner) as user_meals FROM meals WHERE user_id = ?");
        $stmt_m->bind_param("i", $user_id);
        $stmt_m->execute();
        $user_meals = $stmt_m->get_result()->fetch_assoc()['user_meals'] ?? 0;

        // User Deposit
        $stmt_d = $conn->prepare("SELECT SUM(amount) as user_deposit FROM transactions WHERE user_id = ? AND type = 'deposit'");
        $stmt_d->bind_param("i", $user_id);
        $stmt_d->execute();
        $user_deposit = $stmt_d->get_result()->fetch_assoc()['user_deposit'] ?? 0;

        $user_meal_cost = $user_meals * $meal_rate;
        $total_user_cost = round($user_meal_cost + $per_member_utility, 2);
        $balance = round($user_deposit - $total_user_cost, 2);

        $summary[] = [
            "user_id" => $user_id,
            "name" => $user['name'],
            "total_meals" => (float)$user_meals,
            "total_deposit" => (float)$user_deposit,
            "utility_share" => round($per_member_utility, 2),
            "total_cost" => (float)$total_user_cost,
            "balance" => (float)$balance
        ];
    }

    echo json_encode(["status" => "success", "data" => $summary]);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method!"]);
}
?>
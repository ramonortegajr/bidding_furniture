<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Check if item_id is provided
if (!isset($_POST['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'No item specified']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = intval($_POST['item_id']);

// Check if item exists and is active
$check_sql = "SELECT item_id FROM furniture_items WHERE item_id = ? AND status = 'active'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $item_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Item not found or inactive']);
    exit();
}

// Check if already in watchlist
$watch_sql = "SELECT * FROM watchlist WHERE user_id = ? AND item_id = ?";
$watch_stmt = $conn->prepare($watch_sql);
$watch_stmt->bind_param("ii", $user_id, $item_id);
$watch_stmt->execute();
if ($watch_stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Item already in watchlist']);
    exit();
}

// Add to watchlist
$sql = "INSERT INTO watchlist (user_id, item_id, added_date) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $item_id);

if ($stmt->execute()) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true]);
    } else {
        header("Location: item.php?id=" . $item_id);
    }
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Error adding item to watchlist']);
    } else {
        header("Location: item.php?id=" . $item_id . "&error=add_failed");
    }
}
?> 
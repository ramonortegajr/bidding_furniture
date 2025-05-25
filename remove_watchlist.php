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

// Delete from watchlist
$sql = "DELETE FROM watchlist WHERE user_id = ? AND item_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $item_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error removing item from watchlist']);
}
?> 
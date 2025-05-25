<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get notification IDs from POST request
$notification_ids = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];

// If no specific notifications provided, mark all as read
if (empty($notification_ids)) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Convert array to comma-separated string
    $id_string = implode(',', array_map('intval', $notification_ids));
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND notification_id IN ($id_string)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark notifications as read']);
}
?> 
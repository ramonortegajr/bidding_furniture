<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

if (!isset($_POST['notification_id'])) {
    echo json_encode(['error' => 'No notification ID provided']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = intval($_POST['notification_id']);

// Mark notification as read
$update_sql = "UPDATE notifications SET is_read = 1 
               WHERE notification_id = ? AND user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("ii", $notification_id, $user_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to mark notification as read']);
} 
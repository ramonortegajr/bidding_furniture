<?php
require_once 'includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get the last notification check time from session
$last_check = isset($_SESSION['last_notification_check']) ? $_SESSION['last_notification_check'] : 0;
$current_time = time();

// Update the last check time
$_SESSION['last_notification_check'] = $current_time;

// Check for new notifications since last check
$check_query = "SELECT COUNT(*) as new_count 
                FROM notifications 
                WHERE user_id = ? 
                AND UNIX_TIMESTAMP(created_at) > ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ii", $user_id, $last_check);
$stmt->execute();
$result = $stmt->get_result();
$new_count = $result->fetch_assoc()['new_count'];

echo json_encode([
    'success' => true,
    'new_notifications' => $new_count > 0
]);

$conn->close(); 
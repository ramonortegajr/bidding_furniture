<?php
require_once 'includes/config.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if notification_id is provided
if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID not provided']);
    exit;
}

$notification_id = intval($_POST['notification_id']);
$user_id = $_SESSION['user_id'];

// Update notification status
$update_query = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("ii", $notification_id, $user_id);

if ($stmt->execute()) {
    // Get updated unread count
    $count_query = "SELECT COUNT(*) as unread_count 
                    FROM notifications 
                    WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_count = $result->fetch_assoc()['unread_count'];

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark notification as read'
    ]);
}

$conn->close();
?> 
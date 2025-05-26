<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (!isset($_POST['notification_id'])) {
    echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notification_id = intval($_POST['notification_id']);

try {
    $conn->begin_transaction();

    // Mark notification as read
    $update_sql = "UPDATE notifications SET is_read = 1 
                   WHERE notification_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ii", $notification_id, $user_id);
    $update_stmt->execute();

    // Get updated unread count
    $count_sql = "SELECT COUNT(*) as unread_count 
                  FROM notifications 
                  WHERE user_id = ? AND is_read = 0";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $unread_count = $count_stmt->get_result()->fetch_assoc()['unread_count'];

    $conn->commit();

    echo json_encode([
        'success' => true,
        'unread_count' => $unread_count
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Failed to mark notification as read'
    ]);
} 
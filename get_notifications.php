<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$notifications = [];
$unread_count = 0;

// Get notifications
$notifications_sql = "SELECT n.*, f.title as item_title, f.image_url, f.current_price,
                     CASE 
                         WHEN EXISTS (
                             SELECT 1 FROM bids b 
                             WHERE b.item_id = n.item_id 
                             AND b.user_id = ? 
                             AND b.bid_amount = f.current_price
                         ) THEN 'highest'
                         WHEN EXISTS (
                             SELECT 1 FROM bids b 
                             WHERE b.item_id = n.item_id 
                             AND b.user_id = ?
                         ) THEN 'outbid'
                         ELSE NULL
                     END as bid_status
                     FROM notifications n 
                     JOIN furniture_items f ON n.item_id = f.item_id 
                     WHERE n.user_id = ? 
                     ORDER BY n.created_at DESC 
                     LIMIT 5";
$notifications_stmt = $conn->prepare($notifications_sql);
$notifications_stmt->bind_param("iii", $user_id, $user_id, $user_id);
$notifications_stmt->execute();
$result = $notifications_stmt->get_result();

while ($notification = $result->fetch_assoc()) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
    $notifications[] = [
        'notification_id' => $notification['notification_id'],
        'item_id' => $notification['item_id'],
        'message' => $notification['message'],
        'created_at' => $notification['created_at'],
        'is_read' => $notification['is_read'],
        'item_title' => $notification['item_title'],
        'image_url' => $notification['image_url'],
        'bid_status' => $notification['bid_status']
    ];
}

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count
]); 
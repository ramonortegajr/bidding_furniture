<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (!isset($_POST['bid_id']) || !is_numeric($_POST['bid_id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid bid ID']);
    exit();
}

$bid_id = intval($_POST['bid_id']);
$user_id = $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    // Get bid and item information before deletion
    $get_bid_sql = "SELECT b.*, f.title, f.seller_id, f.current_price 
                    FROM bids b 
                    JOIN furniture_items f ON b.item_id = f.item_id 
                    WHERE b.bid_id = ? AND b.user_id = ?";
    $stmt = $conn->prepare($get_bid_sql);
    $stmt->bind_param("ii", $bid_id, $user_id);
    $stmt->execute();
    $bid_info = $stmt->get_result()->fetch_assoc();

    if (!$bid_info) {
        throw new Exception('Bid not found or unauthorized');
    }

    // Get all other bidders for this item
    $get_bidders = "SELECT DISTINCT user_id FROM bids 
                    WHERE item_id = ? AND user_id != ?";
    $stmt = $conn->prepare($get_bidders);
    $stmt->bind_param("ii", $bid_info['item_id'], $user_id);
    $stmt->execute();
    $bidders_result = $stmt->get_result();

    // Delete the bid
    $delete_bid = "DELETE FROM bids WHERE bid_id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_bid);
    $stmt->bind_param("ii", $bid_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('Failed to delete bid');
    }

    // Get the new highest bid
    $get_highest_bid = "SELECT MAX(bid_amount) as highest_bid 
                        FROM bids WHERE item_id = ?";
    $stmt = $conn->prepare($get_highest_bid);
    $stmt->bind_param("i", $bid_info['item_id']);
    $stmt->execute();
    $highest_bid = $stmt->get_result()->fetch_assoc()['highest_bid'];

    // Update the current price
    $new_price = $highest_bid ?: $bid_info['starting_price'];
    $update_price = "UPDATE furniture_items 
                     SET current_price = ? 
                     WHERE item_id = ?";
    $stmt = $conn->prepare($update_price);
    $stmt->bind_param("di", $new_price, $bid_info['item_id']);
    $stmt->execute();

    // Create notification for seller
    $seller_message = "A bid of ₱" . number_format($bid_info['bid_amount'], 2) . 
                     " has been withdrawn from your item: " . $bid_info['title'];
    
    $insert_notification = "INSERT INTO notifications 
                           (user_id, item_id, message, type, created_at, is_read) 
                           VALUES (?, ?, ?, 'bid_delete', NOW(), 0)";
    $stmt = $conn->prepare($insert_notification);
    $stmt->bind_param("iis", $bid_info['seller_id'], $bid_info['item_id'], $seller_message);
    $stmt->execute();

    // Create notifications for other bidders
    $bidder_message = "A bid of ₱" . number_format($bid_info['bid_amount'], 2) . 
                      " has been withdrawn from " . $bid_info['title'] . 
                      ". Current price: ₱" . number_format($new_price, 2);

    while ($bidder = $bidders_result->fetch_assoc()) {
        $stmt = $conn->prepare($insert_notification);
        $stmt->bind_param("iis", $bidder['user_id'], $bid_info['item_id'], $bidder_message);
        $stmt->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 
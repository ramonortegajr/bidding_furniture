<?php
require_once 'config/database.php';

// Get auctions that just ended (within the last minute)
$get_ended_auctions_sql = "SELECT 
    f.item_id,
    f.title,
    f.current_price,
    f.seller_id,
    b.user_id as winner_id,
    u_winner.username as winner_name,
    u_seller.username as seller_name
    FROM furniture_items f
    JOIN bids b ON f.item_id = b.item_id
    JOIN users u_winner ON b.user_id = u_winner.user_id
    JOIN users u_seller ON f.seller_id = u_seller.user_id
    WHERE f.end_time <= NOW()
    AND f.end_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    AND b.bid_amount = f.current_price
    AND f.status != 'completed'";

$ended_auctions = $conn->query($get_ended_auctions_sql);

while ($auction = $ended_auctions->fetch_assoc()) {
    // Create notification for winner
    $winner_message = "🎉 Congratulations! You are the winner of the auction for '{$auction['title']}'! Final winning bid: ₱" . number_format($auction['current_price'], 2);
    $winner_notification_sql = "INSERT INTO notifications (user_id, item_id, message, created_at, bid_status, type) VALUES (?, ?, ?, NOW(), 'highest', 'auction_end')";
    $winner_stmt = $conn->prepare($winner_notification_sql);
    $winner_stmt->bind_param("iis", $auction['winner_id'], $auction['item_id'], $winner_message);
    $winner_stmt->execute();

    // Create notification for seller
    $seller_message = "🏁 Your auction for '{$auction['title']}' has ended! Winner: {$auction['winner_name']} with final bid of ₱" . number_format($auction['current_price'], 2);
    $seller_notification_sql = "INSERT INTO notifications (user_id, item_id, message, created_at, type) VALUES (?, ?, ?, NOW(), 'auction_end')";
    $seller_stmt = $conn->prepare($seller_notification_sql);
    $seller_stmt->bind_param("iis", $auction['seller_id'], $auction['item_id'], $seller_message);
    $seller_stmt->execute();

    // Update item status to completed
    $update_status_sql = "UPDATE furniture_items SET status = 'completed' WHERE item_id = ?";
    $status_stmt = $conn->prepare($update_status_sql);
    $status_stmt->bind_param("i", $auction['item_id']);
    $status_stmt->execute();

    // Notify other bidders
    $notify_other_bidders_sql = "SELECT DISTINCT b.user_id, u.username 
                                FROM bids b 
                                JOIN users u ON b.user_id = u.user_id 
                                WHERE b.item_id = ? 
                                AND b.user_id != ?";
    $other_bidders_stmt = $conn->prepare($notify_other_bidders_sql);
    $other_bidders_stmt->bind_param("ii", $auction['item_id'], $auction['winner_id']);
    $other_bidders_stmt->execute();
    $other_bidders = $other_bidders_stmt->get_result();

    while ($bidder = $other_bidders->fetch_assoc()) {
        $bidder_message = "🔔 Auction ended for '{$auction['title']}'. The winner is {$auction['winner_name']} with a final bid of ₱" . number_format($auction['current_price'], 2);
        $bidder_notification_sql = "INSERT INTO notifications (user_id, item_id, message, created_at, bid_status, type) VALUES (?, ?, ?, NOW(), 'outbid', 'auction_end')";
        $bidder_stmt = $conn->prepare($bidder_notification_sql);
        $bidder_stmt->bind_param("iis", $bidder['user_id'], $auction['item_id'], $bidder_message);
        $bidder_stmt->execute();
    }
}

// Handle auctions with no bids
$no_bids_sql = "SELECT 
    f.item_id,
    f.title,
    f.seller_id,
    u.username as seller_name
    FROM furniture_items f
    JOIN users u ON f.seller_id = u.user_id
    WHERE f.end_time <= NOW()
    AND f.end_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    AND f.status != 'completed'
    AND NOT EXISTS (SELECT 1 FROM bids b WHERE b.item_id = f.item_id)";

$no_bids_auctions = $conn->query($no_bids_sql);

while ($auction = $no_bids_auctions->fetch_assoc()) {
    // Notify seller of no bids
    $message = "⚠️ Your auction for '{$auction['title']}' has ended with no bids.";
    $notification_sql = "INSERT INTO notifications (user_id, item_id, message, created_at, type) VALUES (?, ?, ?, NOW(), 'auction_end')";
    $stmt = $conn->prepare($notification_sql);
    $stmt->bind_param("iis", $auction['seller_id'], $auction['item_id'], $message);
    $stmt->execute();

    // Update item status to completed
    $update_status_sql = "UPDATE furniture_items SET status = 'completed' WHERE item_id = ?";
    $status_stmt = $conn->prepare($update_status_sql);
    $status_stmt->bind_param("i", $auction['item_id']);
    $status_stmt->execute();
}
?> 
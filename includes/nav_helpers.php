<?php
// Ensure database connection is available
if (!isset($conn)) {
    require_once dirname(__DIR__) . '/config/database.php';
}

function checkWinnersNavVisibility($conn, $user_id) {
    if (!$user_id) return false;
    
    // Check if user has any bids
    $bid_check_sql = "SELECT COUNT(*) as bid_count FROM bids WHERE user_id = ?";
    $bid_stmt = $conn->prepare($bid_check_sql);
    $bid_stmt->bind_param("i", $user_id);
    $bid_stmt->execute();
    $bid_result = $bid_stmt->get_result();
    $bid_count = $bid_result->fetch_assoc()['bid_count'];

    // Check if user has sold any items
    $seller_check_sql = "SELECT COUNT(*) as seller_count FROM furniture_items WHERE seller_id = ?";
    $seller_stmt = $conn->prepare($seller_check_sql);
    $seller_stmt->bind_param("i", $user_id);
    $seller_stmt->execute();
    $seller_result = $seller_stmt->get_result();
    $seller_count = $seller_result->fetch_assoc()['seller_count'];

    return ($bid_count > 0 || $seller_count > 0);
}

function getUnreadChatsCount($conn, $user_id) {
    if (!$user_id) return 0;
    
    // Get count of unread messages in all conversations
    $sql = "SELECT COUNT(*) as unread_count
            FROM chat_messages m
            JOIN chat_conversations c ON m.conversation_id = c.conversation_id
            WHERE m.is_read = 0 
            AND m.sender_id != ?
            AND (c.buyer_id = ? OR c.seller_id = ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['unread_count'];
}

function getAllChats($conn, $user_id) {
    if (!$user_id) return [];
    
    $sql = "SELECT 
                c.*,
                f.title as item_title,
                f.image_url,
                CASE 
                    WHEN c.buyer_id = ? THEN s.username
                    ELSE b.username
                END as other_user_name,
                (SELECT COUNT(*) 
                 FROM chat_messages 
                 WHERE conversation_id = c.conversation_id 
                 AND sender_id != ? 
                 AND is_read = 0) as unread_count
            FROM chat_conversations c
            JOIN furniture_items f ON c.item_id = f.item_id
            JOIN users b ON c.buyer_id = b.user_id
            JOIN users s ON c.seller_id = s.user_id
            WHERE c.buyer_id = ? OR c.seller_id = ?
            ORDER BY c.updated_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    return $stmt->get_result();
}
?> 
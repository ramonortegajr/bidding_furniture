<?php
/**
 * Check if the Winners nav item should be visible for a user
 * Shows the Winners nav if user has any bids or has sold any items
 */
function checkWinnersNavVisibility($conn, $user_id) {
    // Check if user has placed any bids
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

    // Show Winners nav if user has bids or has sold items
    return ($bid_count > 0 || $seller_count > 0);
} 
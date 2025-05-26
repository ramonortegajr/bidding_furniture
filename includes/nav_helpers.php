<?php
function checkWinnersNavVisibility($conn, $user_id) {
    if (!isset($user_id)) {
        return ['show' => false, 'new_count' => 0];
    }

    // Check participation and get count of new ended auctions
    $check_participation_sql = "SELECT 
        COUNT(DISTINCT CASE 
            WHEN f.end_time <= NOW() 
            AND f.status = 'active' 
            AND f.notification_sent = 0 
            AND (b.user_id = ? OR f.seller_id = ?) 
            THEN f.item_id 
            ELSE NULL 
        END) as new_ended_count,
        COUNT(DISTINCT CASE 
            WHEN b.user_id = ? OR f.seller_id = ? 
            THEN f.item_id 
            ELSE NULL 
        END) as has_participation
        FROM furniture_items f
        LEFT JOIN bids b ON f.item_id = b.item_id";
    
    $check_stmt = $conn->prepare($check_participation_sql);
    $check_stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    return [
        'show' => $result['has_participation'] > 0,
        'new_count' => $result['new_ended_count']
    ];
}
?> 
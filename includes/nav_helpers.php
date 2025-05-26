<?php
function checkWinnersNavVisibility($conn, $user_id) {
    if (!isset($user_id)) {
        return false;
    }

    $check_participation_sql = "SELECT 1 
        FROM furniture_items f
        LEFT JOIN bids b ON f.item_id = b.item_id
        WHERE (b.user_id = ? OR f.seller_id = ?)
        AND f.end_time < NOW()
        LIMIT 1";
    $check_stmt = $conn->prepare($check_participation_sql);
    $check_stmt->bind_param("ii", $user_id, $user_id);
    $check_stmt->execute();
    return $check_stmt->get_result()->num_rows > 0;
}
?> 
<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav_helpers.php';

// Get the view type from URL parameter
$view_type = isset($_GET['view']) ? $_GET['view'] : 'ended';

// Check if user has bids or sold items
$show_winners_nav = checkWinnersNavVisibility($conn, $_SESSION['user_id'] ?? null);

// Build WHERE conditions based on view type
$where_conditions = [];
if ($view_type === 'ended') {
    $where_conditions[] = "f.end_time < NOW()";
} else {
    $where_conditions[] = "f.status = 'active'";
    $where_conditions[] = "f.end_time > NOW()";
}

$params = [];
$types = "";

// Mark notifications as sent for ended auctions
if (isset($_SESSION['user_id'])) {
    $update_notification_sql = "UPDATE furniture_items 
                              SET notification_sent = 1 
                              WHERE end_time <= NOW() 
                              AND status = 'active'
                              AND notification_sent = 0
                              AND (item_id IN (
                                  SELECT item_id FROM bids WHERE user_id = ?
                              ) OR seller_id = ?)";
    $update_stmt = $conn->prepare($update_notification_sql);
    $update_stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
    $update_stmt->execute();
}

// If user is logged in, only show relevant auctions for ended view
if (isset($_SESSION['user_id']) && $view_type === 'ended') {
    $where_conditions[] = "(EXISTS (SELECT 1 FROM bids WHERE bids.item_id = f.item_id AND bids.user_id = ?) OR f.seller_id = ?)";
    $params[] = $_SESSION['user_id'];
    $params[] = $_SESSION['user_id'];
    $types .= "ii";
}

// Search filter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . $_GET['search'] . "%";
    $where_conditions[] = "(f.title LIKE ? OR w.username LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Timeframe filter
if (isset($_GET['timeframe']) && $_GET['timeframe'] != 'all') {
    switch ($_GET['timeframe']) {
        case 'today':
            $where_conditions[] = "DATE(f.end_time) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "f.end_time >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            break;
        case 'month':
            $where_conditions[] = "f.end_time >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
    }
}

// Sort order
$sort_order = "f.end_time DESC"; // default sort
if (isset($_GET['sort'])) {
    switch ($_GET['sort']) {
        case 'price_high':
            $sort_order = "f.current_price DESC, f.end_time DESC";
            break;
        case 'price_low':
            $sort_order = "f.current_price ASC, f.end_time DESC";
            break;
    }
}

// Get auctions based on view type with user's bid information
$winners_sql = "SELECT 
    f.item_id,
    f.title,
    f.image_url,
    f.current_price as winning_bid,
    f.end_time,
    f.starting_price,
    f.seller_id,
    w.username as winner_name,
    s.username as seller_name,
    b.bid_time as winning_bid_time,
    (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as bid_count,
    ub.bid_amount as user_bid_amount,
    ub.bid_time as user_bid_time,
    CASE 
        WHEN w.user_id = ? THEN 'won'
        WHEN ub.bid_amount IS NOT NULL THEN 'lost'
        WHEN f.seller_id = ? THEN 'sold'
        ELSE 'other'
    END as auction_status
    FROM furniture_items f
    JOIN users s ON f.seller_id = s.user_id
    LEFT JOIN bids b ON f.item_id = b.item_id AND b.bid_amount = f.current_price
    LEFT JOIN users w ON b.user_id = w.user_id
    LEFT JOIN bids ub ON f.item_id = ub.item_id AND ub.user_id = ?
    WHERE " . implode(" AND ", $where_conditions) . "
    GROUP BY f.item_id
    ORDER BY " . $sort_order;

// Add the user_id three times to the params array for the status check and user bid join
if (isset($_SESSION['user_id'])) {
    array_unshift($params, $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
    $types = "iii" . $types;
}

$winners_stmt = $conn->prepare($winners_sql);
if (!empty($params)) {
    $winners_stmt->bind_param($types, ...$params);
}
$winners_stmt->execute();
$winners_result = $winners_stmt->get_result();

// Get notifications for logged-in user
$notifications = null;
$unread_count = 0;
$username = '';
if (isset($_SESSION['user_id'])) {
    // Get username
    $user_sql = "SELECT username FROM users WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $_SESSION['user_id']);
    $user_stmt->execute();
    $username = $user_stmt->get_result()->fetch_assoc()['username'];

    // Get notifications
    $notifications_sql = "SELECT n.*, f.title as item_title, f.image_url, f.current_price
                         FROM notifications n 
                         JOIN furniture_items f ON n.item_id = f.item_id 
                         WHERE n.user_id = ? 
                         ORDER BY n.created_at DESC 
                         LIMIT 5";
    $notifications_stmt = $conn->prepare($notifications_sql);
    $notifications_stmt->bind_param("i", $_SESSION['user_id']);
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->get_result();

    // Count unread notifications
    while ($notification = $notifications->fetch_assoc()) {
        if (!$notification['is_read']) {
            $unread_count++;
        }
    }
    $notifications->data_seek(0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Results - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .bid-status {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
        }
        .status-won {
            background-color: #198754;
            color: white;
        }
        .status-lost {
            background-color: #dc3545;
            color: white;
        }
        .status-sold {
            background-color: #0dcaf0;
            color: white;
        }
        .user-bid {
            background-color: rgba(0,0,0,0.05);
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <!-- Navigation breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active"><?php echo $view_type === 'ended' ? 'Auction Results' : 'Active Auctions'; ?></li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <?php if ($view_type === 'ended'): ?>
                    <i class="fas fa-gavel me-2 text-primary"></i>Auction Results
                <?php else: ?>
                    <i class="fas fa-gavel me-2 text-primary"></i>Active Auctions
                <?php endif; ?>
            </h2>
            <div class="btn-group">
                <a href="?view=<?php echo $view_type === 'ended' ? 'active' : 'ended'; ?>" class="btn <?php echo $view_type === 'ended' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    <?php if ($view_type === 'ended'): ?>
                        <i class="fas fa-gavel me-1"></i>View Active Auctions
                    <?php else: ?>
                        <i class="fas fa-history me-1"></i>View Auction Results
                    <?php endif; ?>
                </a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my_bids.php" class="btn btn-outline-success">
                        <i class="fas fa-list me-1"></i>My Bids
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="view" value="<?php echo $view_type; ?>">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by title or winner..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Timeframe</label>
                        <select name="timeframe" class="form-select">
                            <option value="all" <?php echo (!isset($_GET['timeframe']) || $_GET['timeframe'] == 'all') ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'month') ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="latest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'latest') ? 'selected' : ''; ?>>Latest Ended</option>
                            <option value="price_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : ''; ?>>Price (High to Low)</option>
                            <option value="price_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : ''; ?>>Price (Low to High)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Grid -->
        <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
            <?php if ($winners_result->num_rows > 0): ?>
                <?php while ($item = $winners_result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="card h-100 position-relative">
                            <?php if ($view_type === 'ended'): ?>
                                <?php
                                $status_class = '';
                                $status_text = '';
                                switch ($item['auction_status']) {
                                    case 'won':
                                        $status_class = 'status-won';
                                        $status_text = 'Won!';
                                        break;
                                    case 'lost':
                                        $status_class = 'status-lost';
                                        $status_text = 'Lost';
                                        break;
                                    case 'sold':
                                        $status_class = 'status-sold';
                                        $status_text = 'Sold';
                                        break;
                                }
                                if ($status_text): ?>
                                    <div class="bid-status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <img src="<?php echo $item['image_url'] ? htmlspecialchars($item['image_url']) : 'assets/images/no-image.jpg'; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                 style="height: 200px; object-fit: cover;">
                            
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>Seller: <?php echo htmlspecialchars($item['seller_name']); ?>
                                    </small>
                                </p>
                                
                                <?php if ($view_type === 'ended'): ?>
                                    <div class="mb-2">
                                        <strong>Final Price:</strong> ₱<?php echo number_format($item['winning_bid'], 2); ?>
                                    </div>
                                    <?php if ($item['winner_name']): ?>
                                        <div class="mb-2">
                                            <strong>Winner:</strong> <?php echo htmlspecialchars($item['winner_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['user_bid_amount']): ?>
                                        <div class="user-bid">
                                            <strong>Your Bid:</strong> ₱<?php echo number_format($item['user_bid_amount'], 2); ?>
                                            <br>
                                            <small class="text-muted">
                                                Placed on <?php echo date('M d, Y h:i A', strtotime($item['user_bid_time'])); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="mb-2">
                                        <strong>Current Bid:</strong> ₱<?php echo number_format($item['winning_bid'], 2); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-gavel me-1"></i><?php echo $item['bid_count']; ?> bid(s)
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo $view_type === 'ended' ? 'Ended' : 'Ends'; ?> 
                                        <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-info-circle me-1"></i>View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <?php if ($view_type === 'ended'): ?>
                            No ended auctions found.
                        <?php else: ?>
                            No active auctions found.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
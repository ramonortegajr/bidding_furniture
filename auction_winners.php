<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav_helpers.php';

// Check if user has bids or sold items
$show_winners_nav = checkWinnersNavVisibility($conn, $_SESSION['user_id'] ?? null);

// Redirect if user has no participation
if (!$show_winners_nav) {
    header("Location: furniture_list.php");
    exit();
}

// Build WHERE conditions
$where_conditions = ["f.end_time < NOW()"];
$params = [];
$types = "";

// If user is logged in, only show relevant auctions
if (isset($_SESSION['user_id'])) {
    $where_conditions[] = "(b.user_id = ? OR f.seller_id = ?)";
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

// Get completed auctions and their winners
$winners_sql = "SELECT 
    f.item_id,
    f.title,
    f.image_url,
    f.current_price as winning_bid,
    f.end_time,
    w.username as winner_name,
    s.username as seller_name,
    b.bid_time as winning_bid_time
    FROM furniture_items f
    JOIN bids b ON f.item_id = b.item_id
    JOIN users w ON b.user_id = w.user_id
    JOIN users s ON f.seller_id = s.user_id
    WHERE " . implode(" AND ", $where_conditions) . "
    AND b.bid_amount = f.current_price
    ORDER BY " . $sort_order;

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
    <title>Auction Winners - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-couch me-2"></i>Furniture Bidding
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="furniture_list.php">
                            <i class="fas fa-list me-1"></i>Browse Furniture
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php include 'includes/notifications.php'; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="add_item.php">
                                <i class="fas fa-plus-circle me-1"></i>Add Item
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="my_bids.php"><i class="fas fa-gavel me-2"></i>My Bids</a></li>
                                <li><a class="dropdown-item" href="dashboard.php?tab=watchlist"><i class="fas fa-heart me-2"></i>Watchlist</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-primary btn-sm px-3" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container" style="margin-top: 80px;">
        <!-- Navigation breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Auction Winners</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>
                <i class="fas fa-trophy me-2 text-warning"></i>
                Auction Winners
            </h2>
            <div class="btn-group">
                <a href="furniture_list.php" class="btn btn-outline-primary">
                    <i class="fas fa-gavel me-1"></i>Active Auctions
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
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by title or winner..." 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="recent" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'recent') ? 'selected' : ''; ?>>Most Recent</option>
                            <option value="price_high" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_high') ? 'selected' : ''; ?>>Highest Price</option>
                            <option value="price_low" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_low') ? 'selected' : ''; ?>>Lowest Price</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Time Frame</label>
                        <select name="timeframe" class="form-select">
                            <option value="all" <?php echo (!isset($_GET['timeframe']) || $_GET['timeframe'] == 'all') ? 'selected' : ''; ?>>All Time</option>
                            <option value="today" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="month" <?php echo (isset($_GET['timeframe']) && $_GET['timeframe'] == 'month') ? 'selected' : ''; ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Section -->
        <div class="row">
            <?php if ($winners_result && $winners_result->num_rows > 0): ?>
                <?php while ($winner = $winners_result->fetch_assoc()): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="row g-0">
                                <div class="col-md-4">
                                    <?php if ($winner['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($winner['image_url']); ?>" 
                                             class="img-fluid rounded-start" 
                                             alt="<?php echo htmlspecialchars($winner['title']); ?>"
                                             style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <img src="assets/images/no-image.jpg" 
                                             class="img-fluid rounded-start" 
                                             alt="No image available"
                                             style="height: 200px; object-fit: cover;">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($winner['title']); ?></h5>
                                        <div class="mb-2">
                                            <span class="badge bg-success">
                                                <i class="fas fa-trophy me-1"></i>Winner: <?php echo htmlspecialchars($winner['winner_name']); ?>
                                            </span>
                                        </div>
                                        <p class="card-text">
                                            <small class="text-muted">Seller: <?php echo htmlspecialchars($winner['seller_name']); ?></small><br>
                                            <strong class="text-success">Winning Bid: ₱<?php echo number_format($winner['winning_bid'], 2); ?></strong><br>
                                            <small class="text-muted">
                                                Auction Ended: <?php echo date('M d, Y h:i A', strtotime($winner['end_time'])); ?>
                                            </small>
                                        </p>
                                        <a href="item.php?id=<?php echo $winner['item_id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-info-circle me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>No completed auctions found
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html> 
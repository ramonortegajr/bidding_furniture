<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: furniture_list.php");
    exit();
}

$item_id = intval($_GET['id']);

// Get item details
$sql = "SELECT f.*, c.name as category_name, u.username as seller_name, u.user_id as seller_id 
        FROM furniture_items f 
        JOIN categories c ON f.category_id = c.category_id 
        JOIN users u ON f.seller_id = u.user_id 
        WHERE f.item_id = ? AND f.status = 'active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: furniture_list.php");
    exit();
}

// Get bid history
$bid_sql = "SELECT b.*, u.username,
            CASE 
                WHEN b.bid_amount = (SELECT MAX(bid_amount) FROM bids WHERE item_id = ?) THEN 1
                ELSE 0
            END as is_highest_bidder
            FROM bids b 
            JOIN users u ON b.user_id = u.user_id 
            WHERE b.item_id = ? 
            GROUP BY b.user_id
            ORDER BY b.bid_amount DESC 
            LIMIT 3";
$bid_stmt = $conn->prepare($bid_sql);
$bid_stmt->bind_param("ii", $item_id, $item_id);
$bid_stmt->execute();
$bid_history = $bid_stmt->get_result();

// Get total number of bids
$total_bids_sql = "SELECT COUNT(DISTINCT user_id) as total_bids FROM bids WHERE item_id = ?";
$total_bids_stmt = $conn->prepare($total_bids_sql);
$total_bids_stmt->bind_param("i", $item_id);
$total_bids_stmt->execute();
$total_bids = $total_bids_stmt->get_result()->fetch_assoc()['total_bids'];

// Handle new bid
$bid_error = '';
$bid_success = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    if (isset($_POST['bid_amount'])) {
        $bid_amount = floatval($_POST['bid_amount']);
        
        // Validate bid amount
        if ($bid_amount <= $item['current_price']) {
            $bid_error = "Bid must be higher than current price";
        } else if ($item['seller_id'] == $_SESSION['user_id']) {
            $bid_error = "You cannot bid on your own item";
        } else if (strtotime($item['end_time']) < time()) {
            $bid_error = "This auction has ended";
        } else {
            // Place bid
            $bid_sql = "INSERT INTO bids (item_id, user_id, bid_amount, bid_time) VALUES (?, ?, ?, NOW())";
            $bid_stmt = $conn->prepare($bid_sql);
            $bid_stmt->bind_param("iid", $item_id, $_SESSION['user_id'], $bid_amount);
            
            if ($bid_stmt->execute()) {
                // Update current price
                $update_sql = "UPDATE furniture_items SET current_price = ? WHERE item_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("di", $bid_amount, $item_id);
                $update_stmt->execute();
                
                $bid_success = "Bid placed successfully!";
                
                // Refresh item details
                $stmt->execute();
                $item = $stmt->get_result()->fetch_assoc();
                
                // Refresh bid history
                $bid_stmt->execute();
                $bid_history = $bid_stmt->get_result();
            } else {
                $bid_error = "Error placing bid. Please try again.";
            }
        }
    }
}

// Check if item is in user's watchlist
$in_watchlist = false;
if (isset($_SESSION['user_id'])) {
    $watch_sql = "SELECT * FROM watchlist WHERE user_id = ? AND item_id = ?";
    $watch_stmt = $conn->prepare($watch_sql);
    $watch_stmt->bind_param("ii", $_SESSION['user_id'], $item_id);
    $watch_stmt->execute();
    $in_watchlist = $watch_stmt->get_result()->num_rows > 0;
}

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
    $notifications->data_seek(0); // Reset result pointer
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['title']); ?> - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .item-image {
            max-height: 400px;
            object-fit: contain;
        }
        .bid-history {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
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
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell me-1"></i>Notifications
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-danger notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationsDropdown">
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-bell me-2"></i>Notifications</span>
                                    <?php if ($unread_count > 0): ?>
                                        <span class="badge bg-danger"><?php echo $unread_count; ?> new</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($notifications && $notifications->num_rows > 0): ?>
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <a class="dropdown-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                           href="item.php?id=<?php echo $notification['item_id']; ?>"
                                           onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>)">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo htmlspecialchars($notification['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                                     class="rounded me-2" 
                                                     alt="<?php echo htmlspecialchars($notification['item_title']); ?>"
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                <div class="flex-grow-1">
                                                    <p class="mb-1" style="font-size: 0.9rem;">
                                                        <?php echo htmlspecialchars($notification['message']); ?>
                                                    </p>
                                                    <small class="text-muted">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endwhile; ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item text-center text-primary" href="notifications.php">
                                        <i class="fas fa-list-ul me-1"></i>View All Notifications
                                    </a>
                                <?php else: ?>
                                    <div class="dropdown-item text-center text-muted py-3">
                                        <i class="fas fa-bell-slash me-2"></i>No notifications
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($username); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
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
        <div class="mb-3">
            <a href="furniture_list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Listings
            </a>
        </div>
        <?php if ($bid_error): ?>
            <div class="alert alert-danger"><?php echo $bid_error; ?></div>
        <?php endif; ?>
        <?php if ($bid_success): ?>
            <div class="alert alert-success"><?php echo $bid_success; ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Item Details -->
            <div class="col-md-8">
                <div class="card">
                    <?php if ($item['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top item-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php else: ?>
                        <img src="assets/images/no-image.jpg" class="card-img-top item-image" alt="No image available">
                    <?php endif; ?>
                    <div class="card-body">
                        <h2 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h2>
                        <p class="text-muted">
                            Listed by: <?php echo htmlspecialchars($item['seller_name']); ?><br>
                            Category: <?php echo htmlspecialchars($item['category_name']); ?><br>
                            Condition: <?php echo htmlspecialchars($item['condition_status']); ?>
                        </p>
                        <h4>Description</h4>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                    </div>
                </div>
            </div>

            <!-- Bidding Section -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="card-title">Current Bid</h3>
                        <h2 class="text-primary mb-3">₱<?php echo number_format($item['current_price'], 2); ?></h2>
                        <p class="text-muted">
                            Starting Price: ₱<?php echo number_format($item['starting_price'], 2); ?><br>
                            Auction Ends: <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                        </p>

                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if (strtotime($item['end_time']) > time()): ?>
                                <?php if ($item['seller_id'] == $_SESSION['user_id']): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>You cannot bid on your own item
                                    </div>
                                <?php else: ?>
                                    <form method="POST" class="mb-3">
                                        <div class="input-group mb-3">
                                            <span class="input-group-text">₱</span>
                                            <input type="number" name="bid_amount" class="form-control" step="0.01" min="<?php echo $item['current_price'] + 0.01; ?>" required>
                                            <button type="submit" class="btn btn-primary">Place Bid</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                                
                                <!-- Watchlist Toggle -->
                                <form method="POST" action="<?php echo $in_watchlist ? 'remove_watchlist.php' : 'add_watchlist.php'; ?>" class="mb-3">
                                    <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas <?php echo $in_watchlist ? 'fa-heart' : 'fa-heart-o'; ?>"></i>
                                        <?php echo $in_watchlist ? 'Remove from Watchlist' : 'Add to Watchlist'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">This auction has ended</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary w-100">Login to Bid</a>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Bid History -->
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Bid History</h4>
                        <div class="bid-history">
                            <?php if ($bid_history->num_rows > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php while ($bid = $bid_history->fetch_assoc()): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <strong class="d-block">₱<?php echo number_format($bid['bid_amount'], 2); ?></strong>
                                                    <span class="d-block">
                                                        by <?php echo htmlspecialchars($bid['username']); ?>
                                                        <?php if ($bid['is_highest_bidder']): ?>
                                                            <span class="badge bg-success ms-2">
                                                                <i class="fas fa-trophy me-1"></i>Highest Bidder
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary ms-2">
                                                                <i class="fas fa-arrow-down me-1"></i>Outbid
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <small class="text-muted d-block">
                                                        <i class="far fa-clock me-1"></i>
                                                        <?php echo date('M d, Y h:i A', strtotime($bid['bid_time'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                                <?php if ($total_bids > 3): ?>
                                    <div class="text-center mt-3">
                                        <a href="bid_history.php?item_id=<?php echo $item_id; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-list me-1"></i>See All Bids (<?php echo $total_bids; ?>)
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">
                                    <i class="fas fa-gavel me-2"></i>No bids yet
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function markNotificationRead(notificationId) {
            $.post('mark_notifications_read.php', {
                notification_id: notificationId
            });
        }

        // Update notifications every 30 seconds
        setInterval(function() {
            if (document.getElementById('notificationsDropdown')) {
                $.get('get_notifications.php', function(data) {
                    // Update notification count
                    const unreadCount = data.unread_count;
                    const badge = document.querySelector('.notification-badge');
                    if (unreadCount > 0) {
                        if (badge) {
                            badge.textContent = unreadCount;
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-danger notification-badge';
                            newBadge.textContent = unreadCount;
                            document.getElementById('notificationsDropdown').appendChild(newBadge);
                        }
                    } else if (badge) {
                        badge.remove();
                    }

                    // Update notification list
                    const dropdownMenu = document.querySelector('.notification-dropdown');
                    if (dropdownMenu) {
                        let notificationsHtml = `
                            <div class="dropdown-header d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-bell me-2"></i>Notifications</span>
                                ${unreadCount > 0 ? `<span class="badge bg-danger">${unreadCount} new</span>` : ''}
                            </div>`;

                        if (data.notifications && data.notifications.length > 0) {
                            data.notifications.forEach(notification => {
                                notificationsHtml += `
                                    <a class="dropdown-item ${!notification.is_read ? 'unread' : ''}" 
                                       href="item.php?id=${notification.item_id}"
                                       onclick="markNotificationRead(${notification.notification_id})">
                                        <div class="d-flex align-items-center">
                                            <img src="${notification.image_url || 'assets/images/no-image.jpg'}" 
                                                 class="rounded me-2" 
                                                 alt="${notification.item_title}"
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                            <div class="flex-grow-1">
                                                <p class="mb-1" style="font-size: 0.9rem;">
                                                    ${notification.message}
                                                </p>
                                                <small class="text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    ${new Date(notification.created_at).toLocaleString()}
                                                </small>
                                            </div>
                                        </div>
                                    </a>`;
                            });
                            notificationsHtml += `
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item text-center text-primary" href="notifications.php">
                                    <i class="fas fa-list-ul me-1"></i>View All Notifications
                                </a>`;
                        } else {
                            notificationsHtml += `
                                <div class="dropdown-item text-center text-muted py-3">
                                    <i class="fas fa-bell-slash me-2"></i>No notifications
                                </div>`;
                        }
                        dropdownMenu.innerHTML = notificationsHtml;
                    }
                });
            }
        }, 30000);
    </script>
</body>
</html> 
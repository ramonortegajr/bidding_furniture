<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get username
$username = $user['username'];

// Get user's active bids
$bids_sql = "SELECT b.*, f.title, f.current_price, f.end_time, f.image_url,
             (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as total_bids,
             CASE 
                WHEN f.current_price = b.bid_amount THEN 1 
                ELSE 0 
             END as is_highest_bidder
             FROM bids b 
             JOIN furniture_items f ON b.item_id = f.item_id 
             WHERE b.user_id = ? AND f.status = 'active'
             ORDER BY b.bid_time DESC";
$bids_stmt = $conn->prepare($bids_sql);
$bids_stmt->bind_param("i", $user_id);
$bids_stmt->execute();
$active_bids = $bids_stmt->get_result();

// Get user's listed items (if any)
$listings_sql = "SELECT * FROM furniture_items WHERE seller_id = ? ORDER BY created_at DESC";
$listings_stmt = $conn->prepare($listings_sql);
$listings_stmt->bind_param("i", $user_id);
$listings_stmt->execute();
$my_listings = $listings_stmt->get_result();

// Get user's watchlist
$watchlist_sql = "SELECT f.* FROM watchlist w 
                  JOIN furniture_items f ON w.item_id = f.item_id 
                  WHERE w.user_id = ?";
$watchlist_stmt = $conn->prepare($watchlist_sql);
$watchlist_stmt->bind_param("i", $user_id);
$watchlist_stmt->execute();
$watchlist_items = $watchlist_stmt->get_result();

// Get bidders for seller's items
$bidders_sql = "SELECT b.*, f.title, f.current_price, f.end_time, f.image_url, 
                u.username as bidder_name, u.email as bidder_email,
                (SELECT COUNT(*) FROM bids WHERE item_id = f.item_id) as total_bids
                FROM furniture_items f 
                LEFT JOIN bids b ON f.item_id = b.item_id 
                LEFT JOIN users u ON b.user_id = u.user_id
                WHERE f.seller_id = ? AND f.status = 'active'
                ORDER BY f.item_id, b.bid_amount DESC";
$bidders_stmt = $conn->prepare($bidders_sql);
$bidders_stmt->bind_param("i", $user_id);
$bidders_stmt->execute();
$bidders_result = $bidders_stmt->get_result();

// Group bidders by item
$items_with_bids = [];
while ($row = $bidders_result->fetch_assoc()) {
    if (!isset($items_with_bids[$row['item_id']])) {
        $items_with_bids[$row['item_id']] = [
            'title' => $row['title'],
            'current_price' => $row['current_price'],
            'end_time' => $row['end_time'],
            'image_url' => $row['image_url'],
            'total_bids' => $row['total_bids'],
            'bidders' => []
        ];
    }
    if ($row['bidder_name']) {
        $items_with_bids[$row['item_id']]['bidders'][] = [
            'username' => $row['bidder_name'],
            'email' => $row['bidder_email'],
            'bid_amount' => $row['bid_amount'],
            'bid_time' => $row['bid_time']
        ];
    }
}

// Add this query after the existing queries
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
$notifications = $notifications_stmt->get_result();

// Count unread notifications
$unread_count = 0;
if ($notifications && $notifications->num_rows > 0) {
    while ($notification = $notifications->fetch_assoc()) {
        if (!$notification['is_read']) {
            $unread_count++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <div class="row">
            <!-- Main Content Column -->
            <div class="col-12">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2>Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h2>
                        <p class="text-muted">Here's your bidding activity overview</p>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row dashboard-stats">
                    <div class="col-md-4">
                        <div class="stat-card text-center">
                            <i class="fas fa-gavel fa-2x text-primary mb-2"></i>
                            <h3><?php echo $active_bids->num_rows; ?></h3>
                            <p class="text-muted">Active Bids</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card text-center">
                            <i class="fas fa-list fa-2x text-success mb-2"></i>
                            <h3><?php echo $my_listings->num_rows; ?></h3>
                            <p class="text-muted">My Listings</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card text-center">
                            <i class="fas fa-heart fa-2x text-danger mb-2"></i>
                            <h3><?php echo $watchlist_items->num_rows; ?></h3>
                            <p class="text-muted">Watchlist Items</p>
                        </div>
                    </div>
                </div>

                <!-- Main Content Tabs -->
                <div class="row">
                    <div class="col-12">
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pills-bids-tab" data-bs-toggle="pill" data-bs-target="#pills-bids" type="button">My Bids</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-listings-tab" data-bs-toggle="pill" data-bs-target="#pills-listings" type="button">My Listings</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-watchlist-tab" data-bs-toggle="pill" data-bs-target="#pills-watchlist" type="button">Watchlist</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pills-bidders-tab" data-bs-toggle="pill" data-bs-target="#pills-bidders" type="button">Item Bidders</button>
                            </li>
                        </ul>
                        <div class="tab-content" id="pills-tabContent">
                            <!-- Active Bids Tab -->
                            <div class="tab-pane fade show active" id="pills-bids" role="tabpanel" aria-labelledby="pills-bids-tab">
                                <div class="row">
                                    <?php if ($active_bids->num_rows > 0): ?>
                                        <?php while ($bid = $active_bids->fetch_assoc()): ?>
                                            <div class="col-md-6 col-lg-4 card-wrapper">
                                                <div class="card listing-card">
                                                    <?php if ($bid['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($bid['image_url']); ?>" class="listing-image" alt="<?php echo htmlspecialchars($bid['title']); ?>">
                                                    <?php else: ?>
                                                        <img src="assets/images/no-image.jpg" class="listing-image" alt="No image available">
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($bid['title']); ?></h5>
                                                        <div class="bid-status mb-3">
                                                            <?php if ($bid['is_highest_bidder']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-trophy"></i> Highest Bidder
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark">
                                                                    <i class="fas fa-exclamation-circle"></i> Outbid
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="card-text">
                                                            <span class="d-block mb-2">
                                                                <strong>Your Bid:</strong> 
                                                                <span class="text-primary">₱<?php echo number_format($bid['bid_amount'], 2); ?></span>
                                                            </span>
                                                            <span class="d-block mb-2">
                                                                <strong>Current Price:</strong> 
                                                                <span class="text-success">₱<?php echo number_format($bid['current_price'], 2); ?></span>
                                                            </span>
                                                            <span class="badge bg-info">
                                                                <i class="fas fa-gavel"></i> <?php echo $bid['total_bids']; ?> total bid<?php echo $bid['total_bids'] != 1 ? 's' : ''; ?>
                                                            </span>
                                                        </p>
                                                        <p class="time-left">
                                                            <i class="fas fa-clock"></i> Ends: <?php echo date('M d, Y h:i A', strtotime($bid['end_time'])); ?>
                                                        </p>
                                                        <div class="mt-auto d-flex gap-2">
                                                            <a href="place_bid.php?id=<?php echo $bid['item_id']; ?>&edit=1&amount=<?php echo $bid['bid_amount']; ?>" 
                                                               class="btn btn-outline-primary flex-grow-1">
                                                                <i class="fas fa-edit me-1"></i>Edit Bid
                                                            </a>
                                                            <button class="btn btn-outline-danger flex-grow-1" 
                                                                    onclick="deleteBid(<?php echo $bid['bid_id']; ?>)">
                                                                <i class="fas fa-trash me-1"></i>Delete
                                                            </button>
                                                        </div>
                                                        <div class="mt-2">
                                                            <a href="item.php?id=<?php echo $bid['item_id']; ?>" class="btn btn-primary w-100">View Item</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You haven't placed any bids yet.
                                                <div class="mt-3">
                                                    <a href="furniture_list.php" class="btn btn-primary">Browse Furniture</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- My Listings Tab -->
                            <div class="tab-pane fade" id="pills-listings" role="tabpanel" aria-labelledby="pills-listings-tab">
                                <div class="row">
                                    <?php if ($my_listings->num_rows > 0): ?>
                                        <?php while ($item = $my_listings->fetch_assoc()): ?>
                                            <div class="col-md-6 col-lg-4 card-wrapper">
                                                <div class="card listing-card">
                                                    <?php if ($item['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="listing-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                                    <?php else: ?>
                                                        <img src="assets/images/no-image.jpg" class="listing-image" alt="No image available">
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                        <p class="card-text">
                                                            <strong>Current Price:</strong> ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                                            <strong>Status:</strong> <?php echo ucfirst($item['status']); ?><br>
                                                            <strong>End Time:</strong> <?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?>
                                                        </p>
                                                        <div class="mt-auto">
                                                            <?php if ($item['status'] === 'active'): ?>
                                                                <a href="edit_auction.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">
                                                                    <i class="fas fa-edit me-1"></i>Edit Auction
                                                                </a>
                                                            <?php endif; ?>
                                                            <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-success">
                                                                <i class="fas fa-eye me-1"></i>View
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You haven't listed any items yet.
                                                <div class="mt-3">
                                                    <a href="add_item.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Add New Listing
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Watchlist Tab -->
                            <div class="tab-pane fade" id="pills-watchlist" role="tabpanel" aria-labelledby="pills-watchlist-tab">
                                <div class="row">
                                    <?php if ($watchlist_items->num_rows > 0): ?>
                                        <?php while ($item = $watchlist_items->fetch_assoc()): ?>
                                            <div class="col-md-6 col-lg-4 card-wrapper">
                                                <div class="card listing-card">
                                                    <?php if ($item['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="listing-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                                    <?php else: ?>
                                                        <img src="assets/images/no-image.jpg" class="listing-image" alt="No image available">
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                        <p class="card-text">
                                                            <strong>Current Price:</strong> ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                                            <strong>Ends:</strong> <?php echo date('M d, Y H:i', strtotime($item['end_time'])); ?>
                                                        </p>
                                                        <div class="mt-auto">
                                                            <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">View Item</a>
                                                            <button class="btn btn-danger remove-watchlist" data-item-id="<?php echo $item['item_id']; ?>">
                                                                <i class="fas fa-heart-broken"></i> Remove
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You watchlist is empty.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Item Bidders Tab -->
                            <div class="tab-pane fade" id="pills-bidders" role="tabpanel" aria-labelledby="pills-bidders-tab">
                                <div class="row">
                                    <?php if (!empty($items_with_bids)): ?>
                                        <?php foreach ($items_with_bids as $item_id => $item): ?>
                                            <div class="col-12 mb-4">
                                                <div class="card">
                                                    <div class="card-header bg-white">
                                                        <div class="row align-items-center">
                                                            <div class="col-auto">
                                                                <?php if ($item['image_url']): ?>
                                                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                         class="rounded" 
                                                                         style="width: 100px; height: 100px; object-fit: cover;" 
                                                                         alt="<?php echo htmlspecialchars($item['title']); ?>">
                                                                <?php else: ?>
                                                                    <img src="assets/images/no-image.jpg" 
                                                                         class="rounded" 
                                                                         style="width: 100px; height: 100px; object-fit: cover;" 
                                                                         alt="No image available">
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="col">
                                                                <h5 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h5>
                                                                <p class="text-muted mb-0">
                                                                    Current Price: ₱<?php echo number_format($item['current_price'], 2); ?><br>
                                                                    <span class="badge bg-info">
                                                                        <i class="fas fa-gavel"></i> <?php echo $item['total_bids']; ?> total bid<?php echo $item['total_bids'] != 1 ? 's' : ''; ?>
                                                                    </span>
                                                                    <small class="text-danger ms-2">
                                                                        <i class="fas fa-clock"></i> Ends: <?php echo date('M d, Y h:i A', strtotime($item['end_time'])); ?>
                                                                    </small>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-body p-0">
                                                        <?php if (!empty($item['bidders'])): ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-hover mb-0">
                                                                    <thead class="table-light">
                                                                        <tr>
                                                                            <th>Bidder</th>
                                                                            <th>Email</th>
                                                                            <th>Bid Amount</th>
                                                                            <th>Bid Time</th>
                                                                            <th>Status</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($item['bidders'] as $index => $bidder): ?>
                                                                            <tr>
                                                                                <td><?php echo htmlspecialchars($bidder['username']); ?></td>
                                                                                <td><?php echo htmlspecialchars($bidder['email']); ?></td>
                                                                                <td class="<?php echo $index === 0 ? 'text-success fw-bold' : ''; ?>">
                                                                                    ₱<?php echo number_format($bidder['bid_amount'], 2); ?>
                                                                                </td>
                                                                                <td><?php echo date('M d, Y h:i A', strtotime($bidder['bid_time'])); ?></td>
                                                                                <td>
                                                                                    <?php if ($index === 0): ?>
                                                                                        <span class="badge bg-success">
                                                                                            <i class="fas fa-trophy"></i> Highest Bidder
                                                                                        </span>
                                                                                    <?php else: ?>
                                                                                        <span class="badge bg-secondary">
                                                                                            <i class="fas fa-chart-line"></i> Outbid
                                                                                        </span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="text-center py-4">
                                                                <p class="text-muted mb-0">No bids yet on this item.</p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="card-footer bg-white">
                                                        <a href="item.php?id=<?php echo $item_id; ?>" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-eye"></i> View Item Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-12">
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                You haven't listed any items for auction yet.
                                                <div class="mt-3">
                                                    <a href="add_item.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Add New Listing
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bid_management.js"></script>
    
    <!-- Alert Container -->
    <div id="alert-container" class="position-fixed top-0 start-50 translate-middle-x mt-5" style="z-index: 1050;"></div>
    <script>
        // Handle tab selection from URL parameter
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                const tabElement = document.querySelector(`#pills-${tab}-tab`);
                if (tabElement) {
                    const tabTrigger = new bootstrap.Tab(tabElement);
                    tabTrigger.show();
                }
            }
        });

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
                                let bidStatusBadge = '';
                                if (notification.bid_status) {
                                    if (notification.bid_status === 'highest') {
                                        bidStatusBadge = `<span class="badge bg-success ms-1">
                                            <i class="fas fa-trophy"></i> Highest Bidder
                                        </span>`;
                                    } else if (notification.bid_status === 'outbid') {
                                        bidStatusBadge = `<span class="badge bg-warning text-dark ms-1">
                                            <i class="fas fa-exclamation-circle"></i> Outbid
                                        </span>`;
                                    }
                                }
                                
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
                                                    ${bidStatusBadge}
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

        // Handle watchlist removal
        document.querySelectorAll('.remove-watchlist').forEach(button => {
            button.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                if (confirm('Remove this item from your watchlist?')) {
                    fetch('remove_watchlist.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'item_id=' + itemId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.closest('.listing-card').remove();
                        }
                    });
                }
            });
        });

        function markNotificationRead(notificationId) {
            fetch('mark_notifications_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI if needed
                    const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                    }
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.notification-dropdown');
            const toggle = document.querySelector('#notificationsDropdown');
            if (dropdown && toggle && !dropdown.contains(event.target) && !toggle.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html> 
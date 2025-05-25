<?php
session_start();
require_once 'config/database.php';

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

// Get categories for filter
$categories_sql = "SELECT * FROM categories GROUP BY name ORDER BY name";
$categories_result = $conn->query($categories_sql);

// Build query based on filters
$where_conditions = ["status = 'active'"];
$params = [];
$types = "";

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $where_conditions[] = "category_id = ?";
    $params[] = intval($_GET['category']);
    $types .= "i";
}

if (isset($_GET['condition']) && !empty($_GET['condition'])) {
    $where_conditions[] = "condition_status = ?";
    $params[] = $_GET['condition'];
    $types .= "s";
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $where_conditions[] = "(f.title LIKE ? OR f.description LIKE ?)";
    $search_term = "%" . $_GET['search'] . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// Sorting
$sort_options = [
    'price_asc' => 'current_price ASC',
    'price_desc' => 'current_price DESC',
    'newest' => 'created_at DESC',
    'ending_soon' => 'end_time ASC'
];
$sort = isset($_GET['sort']) && array_key_exists($_GET['sort'], $sort_options) 
    ? $sort_options[$_GET['sort']] 
    : 'created_at DESC';

// Construct and execute query
$sql = "SELECT f.*, c.name as category_name, u.username as seller_name,
        (SELECT COUNT(*) FROM bids b WHERE b.item_id = f.item_id) as bid_count,
        (SELECT MAX(b2.bid_amount) FROM bids b2 WHERE b2.item_id = f.item_id AND b2.user_id = ?) as user_bid 
        FROM furniture_items f 
        JOIN categories c ON f.category_id = c.category_id 
        JOIN users u ON f.seller_id = u.user_id
        WHERE " . implode(" AND ", $where_conditions) . 
        " ORDER BY " . $sort;

// Add user_id to the beginning of params array
array_unshift($params, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);
$types = "i" . $types; // Add integer type for user_id

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Furniture - Furniture Bidding System</title>
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
                        <a class="nav-link active" href="furniture_list.php">
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
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search furniture...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($category = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Condition</label>
                    <select name="condition" class="form-select">
                        <option value="">Any</option>
                        <option value="New" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'New') ? 'selected' : ''; ?>>New</option>
                        <option value="Used" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Used') ? 'selected' : ''; ?>>Used</option>
                        <option value="Refurbished" <?php echo (isset($_GET['condition']) && $_GET['condition'] == 'Refurbished') ? 'selected' : ''; ?>>Refurbished</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="newest" <?php echo (!isset($_GET['sort']) || $_GET['sort'] == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_asc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_asc') ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'price_desc') ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="ending_soon" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'ending_soon') ? 'selected' : ''; ?>>Ending Soon</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="row">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($item = $result->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card furniture-card">
                            <?php if ($item['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="card-img-top furniture-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <img src="assets/images/no-image.jpg" class="card-img-top furniture-image" alt="No image available">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                                
                                <!-- Item Details -->
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-tag me-2 text-muted"></i>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="fas fa-info-circle me-2 text-muted"></i>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($item['condition_status']); ?></span>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user me-2 text-muted"></i>
                                        <span class="text-muted">Seller: <?php echo htmlspecialchars($item['seller_name']); ?></span>
                                    </div>
                                </div>

                                <!-- Bid Information -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <h6 class="mb-0">Current Bid:</h6>
                                            <span class="fs-5 fw-bold text-success">₱<?php echo number_format($item['current_price'], 2); ?></span>
                                        </div>
                                        <span class="badge bg-info">
                                            <i class="fas fa-gavel me-1"></i><?php echo $item['bid_count']; ?> bid<?php echo $item['bid_count'] != 1 ? 's' : ''; ?>
                                        </span>
                                    </div>
                                    <?php if (isset($item['user_bid']) && $item['user_bid'] > 0): ?>
                                        <div class="text-center mb-2">
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i>Your bid: ₱<?php echo number_format($item['user_bid'], 2); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Time Remaining -->
                                <div class="mb-3 text-center">
                                    <i class="fas fa-clock me-1 text-danger"></i>
                                    <span class="text-danger">
                                        <?php 
                                        $end_time = strtotime($item['end_time']);
                                        $now = time();
                                        $time_left = $end_time - $now;
                                        
                                        if ($time_left > 0) {
                                            $days = floor($time_left / (60 * 60 * 24));
                                            $hours = floor(($time_left % (60 * 60 * 24)) / (60 * 60));
                                            if ($days > 0) {
                                                echo $days . "d " . $hours . "h left";
                                            } else {
                                                $minutes = floor(($time_left % (60 * 60)) / 60);
                                                echo $hours . "h " . $minutes . "m left";
                                            }
                                        } else {
                                            echo "Auction Ended";
                                        }
                                        ?>
                                    </span>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <?php if ($time_left > 0 && (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $item['seller_id'])): ?>
                                        <a href="place_bid.php?id=<?php echo $item['item_id']; ?>" class="btn btn-success">
                                            <i class="fas fa-gavel me-1"></i>Place Bid
                                        </a>
                                    <?php endif; ?>
                                    <a href="item.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-info-circle me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i>No furniture items found matching your criteria
                    </div>
                </div>
            <?php endif; ?>
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
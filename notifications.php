<?php
session_start();
require_once 'config/database.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get username for the logged-in user
$username = '';
$user_sql = "SELECT username FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $_SESSION['user_id']);
$user_stmt->execute();
$username = $user_stmt->get_result()->fetch_assoc()['username'];

// Get all notifications for the user
$notifications_sql = "SELECT n.*, f.title as item_title, f.image_url,
                     (SELECT MAX(bid_amount) FROM bids WHERE item_id = f.item_id) as current_bid,
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
                     ORDER BY n.created_at DESC";
$stmt = $conn->prepare($notifications_sql);
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();

// Count unread notifications
$unread_count = 0;
$all_notifications = array(); // Store all notifications in an array
while ($notification = $notifications->fetch_assoc()) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
    $all_notifications[] = $notification; // Store each notification
}

// Mark all as read
$mark_read_sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = $conn->prepare($mark_read_sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Furniture Bidding System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation_common.php'; ?>

    <div class="container" style="margin-top: 80px;">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">Your Notifications</h2>
                
                <?php if (!empty($all_notifications)): ?>
                    <div class="list-group">
                        <?php foreach ($all_notifications as $notification): ?>
                            <a href="item.php?id=<?php echo $notification['item_id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo !$notification['is_read'] ? 'unread-notification' : ''; ?>"
                               onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>)">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($notification['image_url'] ?: 'assets/images/no-image.jpg'); ?>" 
                                             class="rounded me-3" 
                                             alt="<?php echo htmlspecialchars($notification['item_title']); ?>"
                                             style="width: 60px; height: 60px; object-fit: cover;">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['item_title']); ?></h6>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="ms-2 unread-dot"></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                                <?php if ($notification['bid_status']): ?>
                                                    <span class="badge <?php echo $notification['bid_status'] === 'highest' ? 'bg-success' : 'bg-warning text-dark'; ?> ms-1">
                                                        <?php if ($notification['bid_status'] === 'highest'): ?>
                                                            <i class="fas fa-trophy"></i> Highest Bidder
                                                        <?php else: ?>
                                                            <i class="fas fa-exclamation-circle"></i> Outbid
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-primary">
                                            Current Bid: ₱<?php echo number_format($notification['current_bid'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>You have no notifications
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .unread-notification {
            background-color: rgba(13, 110, 253, 0.05) !important;
            border-left: 4px solid #0d6efd !important;
        }
        .unread-dot {
            width: 8px;
            height: 8px;
            background-color: #0d6efd;
            border-radius: 50%;
            display: inline-block;
        }
        .list-group-item {
            transition: all 0.3s ease;
        }
        .list-group-item:hover {
            transform: translateX(5px);
            background-color: rgba(0, 0, 0, 0.02);
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
                    // Remove unread styling when clicked
                    const notification = event.currentTarget;
                    notification.classList.remove('unread-notification');
                    const unreadDot = notification.querySelector('.unread-dot');
                    if (unreadDot) {
                        unreadDot.remove();
                    }

                    // Update notification badge in navbar if it exists
                    const navBadge = document.querySelector('.notification-badge');
                    if (navBadge && data.unread_count > 0) {
                        navBadge.textContent = data.unread_count;
                    } else if (navBadge) {
                        navBadge.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }
    </script>
</body>
</html> 
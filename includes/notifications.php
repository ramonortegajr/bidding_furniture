<?php
// Get notifications for logged-in user
$notifications = null;
$unread_count = 0;

if (isset($_SESSION['user_id'])) {
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

<!-- Add the required JavaScript -->
<script>
function markNotificationRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI to reflect read status
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread');
                const newBadge = notificationElement.querySelector('.badge.bg-danger');
                if (newBadge) {
                    newBadge.remove();
                }
            }

            // Update notification count
            const countBadge = document.querySelector('.notification-badge');
            if (countBadge) {
                const currentCount = parseInt(countBadge.textContent);
                if (currentCount > 1) {
                    countBadge.textContent = currentCount - 1;
                } else {
                    countBadge.remove();
                }
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

// Check for new notifications every 30 seconds
setInterval(function() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_notifications) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
}, 30000);
</script> 
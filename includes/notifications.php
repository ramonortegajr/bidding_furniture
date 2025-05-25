<?php
if (isset($_SESSION['user_id'])) {
    // Get unread notifications count
    $unread_count_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($unread_count_sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $unread_count = $stmt->get_result()->fetch_assoc()['count'];

    // Get recent notifications
    $notifications_sql = "SELECT n.*, f.title as item_title 
                         FROM notifications n 
                         JOIN furniture_items f ON n.item_id = f.item_id 
                         WHERE n.user_id = ? 
                         ORDER BY n.created_at DESC 
                         LIMIT 5";
    $stmt = $conn->prepare($notifications_sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $notifications = $stmt->get_result();
}
?>

<!-- Notifications Dropdown -->
<?php if (isset($_SESSION['user_id'])): ?>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle position-relative" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Notifications
            <?php if ($unread_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $unread_count; ?>
                </span>
            <?php endif; ?>
        </a>
        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationsDropdown" style="min-width: 300px; max-height: 400px; overflow-y: auto;">
            <h6 class="dropdown-header">Notifications</h6>
            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>
                    <a class="dropdown-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>" 
                       href="item.php?id=<?php echo $notification['item_id']; ?>"
                       onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>)">
                        <small class="text-muted d-block">
                            <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                        </small>
                        <?php echo htmlspecialchars($notification['message']); ?>
                    </a>
                <?php endwhile; ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center" href="notifications.php">View All Notifications</a>
            <?php else: ?>
                <div class="dropdown-item text-muted">No notifications</div>
            <?php endif; ?>
        </div>
    </li>
<?php endif; ?>

<script>
function markNotificationRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    });
}
</script> 